<?php

namespace App\Listeners;

use App\Models\User;
use Filament\Notifications\Notification;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class MediaCreatedListener
{
    public function handle(MediaHasBeenAddedEvent $event)
    {
        $media = $event->media;
        $model = $media->model;

        if ($model instanceof User) {
            $client = new Client([
                'base_uri' => env('RECOGNIZER_ENDPOINT', 'http://127.0.0.1:8000'),
                'timeout'  => 30.0,
            ]);

            try {
                $response = $client->request('POST', 'train', [
                    'query' => [
                        'name' => strval($model->id),
                    ],
                    'multipart' => [
                        [
                            'name'     => 'name',
                            'contents' => strval($model->id),
                        ],
                        [
                            'name'     => 'images',
                            'contents' => fopen($media->getPath(), 'r'),
                            'filename' => $media->file_name
                        ]
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = json_decode($response->getBody()->getContents(), true);

                if ($statusCode >= 200 && $statusCode < 300) {
                    Notification::make("success")
                        ->title("Успех")
                        ->success()
                        ->body("$media->file_name had been used in training successfully")
                        ->send();
                } else {
                    $media->delete();

                    logger()->error('API request failed', [
                        'status_code' => $statusCode,
                        'response' => $responseBody
                    ]);

                    Notification::make("danger")
                        ->title("Ошибка")
                        ->danger()
                        ->body("$media->file_name had been used in training failed")
                        ->send();
                }
            } catch (\Throwable $exception) {
                $media->delete();

                logger()->error('API request failed', [
                    'status_code' => 500,
                    'response' => $exception->getMessage()
                ]);

                Notification::make("danger")
                    ->title("Ошибка")
                    ->danger()
                    ->body("$media->file_name had been used in training failed: " . $exception->getMessage())
                    ->send();
            }
        }

    }
}
