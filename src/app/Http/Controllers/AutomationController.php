<?php

namespace App\Http\Controllers;

use App\FolderCopyConfig;
use App\Resources\FolderCopyConfigResource;
use App\Resources\SmbCifsCopyConfigResource;
use App\Resources\TelegramConfigResource;
use App\Resources\WebRequestConfigResource;
use App\SmbCifsCopyConfig;
use App\TelegramConfig;
use App\WebRequestConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutomationController extends Controller
{
    public function telegramConfigIndex()
    {
        return TelegramConfigResource::collection(
            TelegramConfig::with(['detectionProfiles'])->orderByDesc('created_at')->get()
        );
    }

    public function makeTelegramConfig(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:telegram_configs',
            'token' => 'required',
            'chat_id' => 'required',
        ]);

        $config = TelegramConfig::create([
            'name' => $request->get('name'),
            'token' => $request->get('token'),
            'chat_id' => $request->get('chat_id')
        ]);

        return TelegramConfigResource::make($config);
    }

    public function webRequestConfigIndex()
    {
        return WebRequestConfigResource::collection(
            WebRequestConfig::with(['detectionProfiles'])->orderByDesc('created_at')->get()
        );
    }

    public function makeWebRequestConfig(Request $request)
    {
        Log::info($request);
        $request->validate([
            'name' => 'required|unique:web_request_configs',
            'url' => 'required',
        ]);
        Log::info($request->get('is_post', false));
        Log::info($request->get('body_json', ''));

        $config = WebRequestConfig::create($request->all());

        return WebRequestConfigResource::make($config);
    }

    public function folderCopyConfigIndex()
    {
        return FolderCopyConfigResource::collection(
            FolderCopyConfig::with(['detectionProfiles'])->orderByDesc('created_at')->get()
        );
    }

    public function makeFolderCopyConfig(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:folder_copy_configs',
            'copy_to' => 'required'
        ]);

        $config = FolderCopyConfig::create([
            'name' => $request->get('name'),
            'copy_to' => $request->get('copy_to'),
            'overwrite' => $request->get('overwrite', false)
        ]);

        return FolderCopyConfigResource::make($config);
    }

    public function smbCifsCopyConfigIndex()
    {
        return SmbCifsCopyConfigResource::collection(
            SmbCifsCopyConfig::with(['detectionProfiles'])->orderByDesc('created_at')->get()
        );
    }

    public function makeSmbCifsCopyConfig(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:folder_copy_configs',
            'servicename' => 'required',
            'user' => 'required',
            'password' => 'required',
            'remote_dest' => 'required'
        ]);

        $config = SmbCifsCopyConfig::create([
            'name' => $request->get('name'),
            'servicename' => $request->get('servicename'),
            'user' => $request->get('user'),
            'password' => $request->get('password'),
            'remote_dest' => $request->get('remote_dest'),
            'overwrite' => $request->get('overwrite', false)
        ]);

        return SmbCifsCopyConfigResource::make($config);
    }
}
