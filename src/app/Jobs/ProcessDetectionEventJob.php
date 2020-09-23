<?php

namespace App\Jobs;

use App\AiPrediction;
use App\DeepstackClient;
use App\DeepstackResult;
use App\DetectionEvent;
use App\DetectionMask;
use App\DetectionProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessDetectionEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;

    /**
     * Create a new job instance.
     *
     * @param  DetectionEvent  $event
     * @return void
     */
    public function __construct(DetectionEvent $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() // todo: inject deepstack processor container
    {
        $client = new DeepstackClient();
        $response = $client->detection($this->event->image_file_name);

        $this->event->deepstack_response = $response;
        $this->event->save();

        $result = new DeepstackResult($response);

        $matchedProfiles = [];

        foreach ($result->getPredictions() as $prediction) {
            $aiPrediction = AiPrediction::create([
                'object_class' => $prediction->label,
                'confidence' => $prediction->confidence,
                'x_min' => $prediction->x_min,
                'x_max' => $prediction->x_max,
                'y_min' => $prediction->y_min,
                'y_max' => $prediction->y_max,
                'detection_event_id' => $this->event->id
            ]);

            $relevantProfiles =
                DetectionProfile::
                      whereRaw('\''.$this->event->image_file_name .'\' like CONCAT(\'%\', file_pattern ,\'%\')')
                    ->where('object_classes', 'like', '%"'.$prediction->label.'"%')
                    ->where('min_confidence', '<=', $prediction->confidence)
                    ->get();

            foreach($relevantProfiles as $profile) {

                $profileMatch = true;

                if ($profile->use_mask) {
                    $mask = new DetectionMask($profile->slug.'.png');
                    $profileMatch = $mask->is_object_outside_mask(
                        $aiPrediction->x_min,
                        $aiPrediction->x_max,
                        $aiPrediction->y_min,
                        $aiPrediction->y_max
                    );
                }

                if ($profileMatch) {
                    if(!in_array($profile, $matchedProfiles)) {
                        array_push($matchedProfiles, $profile);
                    }
                    $profile->aiPredictions()->attach($aiPrediction->id);
                }
            }
        }

        foreach ($matchedProfiles as $profile) {
            $profile->load(['telegramConfigs']);
            foreach ($profile->telegramConfigs as $config) {
                ProcessTelegramJob::dispatch($this->event, $config);
            }

            foreach ($profile->webRequestConfigs as $config) {
                ProcessWebRequestJob::dispatch($this->event, $config);
            }

            foreach($profile->folderCopyConfigs as $config) {
                ProcessFolderCopyJob::dispatch($this->event, $config, $profile);
            }

            foreach($profile->smbCifsCopyConfigs as $config) {
                ProcessSmbCifsCopyJob::dispatch($this->event, $config, $profile);
            }
        }
    }
}