<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use GuzzleHttp\Client;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('reset-faces')
                ->requiresConfirmation()
                ->action(function () {
                    $client = new Client([
                        'base_uri' => env('RECOGNIZER_ENDPOINT', 'http://127.0.0.1:8000'),
                        'timeout'  => 30.0,
                    ]);

                    try {
                        $response = $client->request('POST', 'reset');

                        $statusCode = $response->getStatusCode();
                        $responseBody = json_decode($response->getBody()->getContents(), true);

                        if ($statusCode >= 200 && $statusCode < 300) {

                            Notification::make("success")
                                ->title("Успех")
                                ->success()
                                ->body("Данные пользователей были сброшены")
                                ->send();
                        } else {
                            logger()->error('API request failed', [
                                'status_code' => $statusCode,
                                'response' => $responseBody
                            ]);

                            Notification::make("danger")
                                ->title("Ошибка")
                                ->danger()
                                ->body("Сброс пользователей не удался")
                                ->send();
                        }
                    } catch (\Throwable $exception) {
                        logger()->error('API request failed', [
                            'status_code' => 500,
                            'response' => $exception->getMessage()
                        ]);

                        Notification::make("danger")
                            ->title("Ошибка")
                            ->danger()
                            ->body("Сброс пользователей не удался" . $exception->getMessage())
                            ->send();
                    }

                    User::get()->each(function ($user) use ($client) {

                        try {
                            $multipart = [];
                            $media = $user->getMedia('dataset');

                            if (empty($media)) {
                                return;
                            }

                            foreach ($media as $mediaItem) {
                                $multipart[] = [
                                    'name'     => 'images',
                                    'contents' => fopen($mediaItem->getPath(), 'r'),
                                    'filename' => $mediaItem->file_name
                                ];
                            }

                            $response = $client->request('POST', 'train', [
                                'query' => [
                                    'name' => strval($user->id),
                                ],
                                'multipart' => [
                                    [
                                        'name'     => 'name',
                                        'contents' => strval($user->id),
                                    ],
                                    ...$multipart,
                                ]
                            ]);

                            $statusCode = $response->getStatusCode();
                            $responseBody = json_decode($response->getBody()->getContents(), true);

                            if ($statusCode >= 200 && $statusCode < 300) {
                                Notification::make("success")
                                    ->title("Успех")
                                    ->success()
                                    ->body("$user->id had been used in training successfully")
                                    ->send();
                            } else {
                                logger()->error('API request failed', [
                                    'status_code' => $statusCode,
                                    'response' => $responseBody
                                ]);

                                Notification::make("danger")
                                    ->title("Ошибка")
                                    ->danger()
                                    ->body("$user->id had been used in training failed")
                                    ->send();
                            }
                        } catch (\Throwable $exception) {
                            dd($media);

                            logger()->error('API request failed', [
                                'status_code' => 500,
                                'response' => $exception->getMessage()
                            ]);

                            Notification::make("danger")
                                ->title("Ошибка")
                                ->danger()
                                ->body("$user->id had been used in training failed: " . $exception->getMessage())
                                ->send();
                        }
                    });
                }),
        ];
    }
}
