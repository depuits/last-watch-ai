<template>
    <div class="component-container">
        <div class="lw-breadcrumb">
            <nav class="breadcrumb" aria-label="breadcrumbs">
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/events">Detection Events</a></li>
                    <li class="is-active"><a href="#" aria-current="page">Details</a></li>
                </ul>
            </nav>
            <nav class="lw-prev-next">
                <a v-if="prevEvent" :href="prevEvent.id">
                    ←
                </a>
                <span v-else>
                    ←
                </span>
                <a v-if="nextEvent" :href="nextEvent.id">
                    →
                </a>
                <span v-else>
                  →
                </span>
            </nav>
        </div>
        <title-header>
            <template v-slot:title>
                Detection Event
            </template>
            <template v-slot:subtitle>
                {{ event.image_file_name }}
            </template>
            <template v-slot:meta>
                <div class="control">
                    <div class="tags has-addons">
                        <span class="tag">Relevant</span>
                        <span v-if="relevant" class="tag is-success">Yes</span>
                        <span v-else class="tag is-success is-light">No</span>
                    </div>
                </div>
                <div class="control">
                    <div class="tags has-addons">
                        <span class="tag">Automations Run</span>
                        <a :class="'tag is-success' + (automations === 0 ? ' is-light' : '')">{{ automations }}</a>
                    </div>
                </div>
                <div v-if="automationErrors > 0" class="control">
                    <div class="tags has-addons">
                        <span class="tag">Automation Errors</span>
                        <a href="/errors" class="tag is-danger">{{ automationErrors }}</a>
                    </div>
                </div>
            </template>
        </title-header>

        <div class="columns reverse-columns" style="margin-left:-0.75rem;">
            <div v-if="!loading" class="column is-one-third">
                <div class="content" :title="event.occurred_at | dateStr">
                    <span class="icon">
                        <b-icon icon="clock"></b-icon>
                    </span>
                    <span>{{ event.occurred_at | dateStrRelative }}</span>
                </div>
                <div class="content">
                    <a :href="imageFile" download>
                        <span class="icon">
                            <b-icon icon="image"></b-icon>
                        </span>
                        <span>Download Image File</span>
                    </a>
                </div>
                <b-menu class="mb-4">
                    <b-menu-list label="Matched Profiles">
                        <b-menu-item
                            v-for="profile in event.pattern_matched_profiles"
                            @click="toggleActiveProfile(profile)"
                            :key="profile.key"
                            :disabled="!profile.is_profile_active">

                            <template slot="label" slot-scope="props">
                                <p class="heading is-size-6">
                                    <b-icon :icon="getIcon(profile)"></b-icon>
                                    {{ profile.name }}
                                    <span v-if="!profile.is_profile_active">(inactive)</span>
                                </p>
                            </template>

                            <div v-if="getPredictions(profile).length > 0">
                                <li v-for="prediction in getPredictions(profile)">
                                    <span v-if="prediction.is_masked">(masked)</span>
                                    <span v-else-if="prediction.is_smart_filtered">(filtered)</span>
                                    {{ prediction.object_class }} - {{ prediction.confidence | percentage }}
                                </li>
                            </div>
                            <p v-else>
                                No relevant objects detected.
                            </p>
                        </b-menu-item>
                    </b-menu-list>
                </b-menu>



            </div>
            <div class="column is-two-thirds">
                <canvas id="event-snapshot" ref="event-snapshot" style="width:100%;"></canvas>
            </div>
        </div>
    </div>
</template>

<script>
    let Facade = require('facade.js');

    export default {
        name: "ShowDetectionEvent",

        props: ['id'],

        data() {
            return {
                event: {},
                nextEvent: null,
                prevEvent: null,
                predictions: [],
                profiles: [],
                selectedProfile: {},
                highlight: false,
                loading: true
            }
        },

        mounted () {
            this.getData();
        },

        computed: {
            automations() {
                if (this.event && this.event.automationResults) {
                    return this.event.automationResults.filter(a => !a.is_error).length;
                }
                return 0;
            },
            automationErrors() {
                if (this.event && this.event.automationResults) {
                    return this.event.automationResults.filter(a => a.is_error).length;
                }
                return 0;
            },
            imageWidth() {
                if (this.event && this.event.image_dimensions) {
                    return parseInt(this.event.image_dimensions.substring(0, this.event.image_dimensions.indexOf('x')));
                }
                return 0;
            },
            imageHeight() {
                if (this.event && this.event.image_dimensions) {
                    return parseInt(this.event.image_dimensions.substring(this.event.image_dimensions.indexOf('x') + 1));
                }
                return 0;
            },
            imageFile() {
                return this.event ? '/storage/' + this.event.image_file_name : '';
            },
            relevant() {
                if (this.event && this.event.ai_predictions) {
                    for (let i = 0; i < this.event.ai_predictions.length; i++) {
                        let prediction = this.event.ai_predictions[i];
                        for (let j = 0; j < prediction.detection_profiles.length; j++) {
                            if (!prediction.detection_profiles[j].is_masked &&
                                !prediction.detection_profiles[j].is_smart_filtered) {
                                return true;
                            }
                        }
                    }
                }
                return false;
            }
        },

        filters: {
            percentage(value) {
                return (value * 100) + "%";
            }
        },

        methods: {
            getData() {
                axios.get(`/api/events/${this.id}`)
                    .then(response => {
                        this.event = response.data.data;

                        this.event.ai_predictions.forEach(ap => {
                            ap.detection_profiles.forEach(dp => {
                                this.profiles.push(dp);
                            });
                        });

                        this.profiles = _.uniqBy(this.profiles, function(p) {
                            return p.id;
                        });

                        this.loading = false;
                        this.draw();
                    });

                axios.get(`/api/events/${this.id}/prev`)
                    .then(response => {
                        this.prevEvent = response.data.data;
                    });

                axios.get(`/api/events/${this.id}/next`)
                    .then(response => {
                        this.nextEvent = response.data.data;
                    });
            },

            hasUnmaskedUnfilteredPredictions(profile) {
                let predictions = this.getPredictions(profile);

                for (let i = 0; i < predictions.length; i++) {
                    if (!predictions[i].is_masked && !predictions[i].is_smart_filtered) {
                        return true;
                    }
                }

                return false;
            },

            getPredictions(profile) {
                let predictions = [];

                for (let i = 0; i < this.event.ai_predictions.length; i++) {
                    let prediction = this.event.ai_predictions[i];
                    for (let j = 0; j < prediction.detection_profiles.length; j++) {
                        if (prediction.detection_profiles[j].id === profile.id) {
                            prediction.is_masked = prediction.detection_profiles[j].is_masked;
                            prediction.is_smart_filtered = prediction.detection_profiles[j].is_smart_filtered;
                            predictions.push(prediction);
                            break;
                        }
                    }
                }

                return predictions;
            },

            toggleActiveProfile(profile) {

                if (this.selectedProfile.id === profile.id) {
                    this.selectedProfile = {};
                    this.highlight = false;
                    this.event.ai_predictions.forEach(p => p.is_masked = false);
                    this.event.ai_predictions.forEach(p => p.is_smart_filtered = false);
                }
                else {
                    this.selectedProfile = profile;
                    this.highlight = true;
                }

                this.draw();
            },

            getIcon(profile) {
                if (!profile.is_profile_active) {
                    return "ban";
                }

                if (this.hasUnmaskedUnfilteredPredictions(profile)) {
                    return 'check';
                }

                return 'times';
            },

            draw() {
                let predictions = this.highlight ?
                    this.getPredictions(this.selectedProfile) :
                    [];

                let mask_name = this.highlight ?
                    this.selectedProfile.use_mask ? this.selectedProfile.slug + '.png' : null :
                    null;

                let canvas = document.getElementById('event-snapshot');
                canvas.width = this.imageWidth;
                canvas.height = this.imageHeight;

                let stage = new Facade(document.querySelector('#event-snapshot')),
                    image = new Facade.Image(this.imageFile, {
                        x: this.imageWidth / 2,
                        y: this.imageHeight / 2,
                        height: this.imageHeight,
                        width: this.imageWidth,
                        anchor: 'center'
                    });

                let mask = null;
                if (mask_name) {
                    mask = new Facade.Image('/storage/masks/' + mask_name, {
                        x: this.imageWidth / 2,
                        y: this.imageHeight / 2,
                        height: this.imageHeight,
                        width: this.imageWidth,
                        anchor: 'center'
                    });
                }

                let rects = [];
                predictions.forEach(prediction => {
                    let color = this.highlight ? '#7957d5' : 'red';
                    rects.push(new Facade.Rect({
                        x: prediction.x_min,
                        y: prediction.y_min,
                        width: prediction.x_max - prediction.x_min,
                        height: prediction.y_max - prediction.y_min,
                        lineWidth: 4,
                        strokeStyle: prediction.is_masked || prediction.is_smart_filtered ? 'gray' : color,
                        fillStyle: 'rgba(0, 0, 0, 0)'
                    }));
                });

                stage.draw(function () {
                    this.clear();

                    this.addToStage(image);

                    if (mask) this.addToStage(mask);

                    for (let i = 0; i < rects.length; i++) {
                        this.addToStage(rects[i]);
                    }
                });

            }
        }
    }
</script>

<style scoped>

</style>
