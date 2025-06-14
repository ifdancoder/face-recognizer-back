<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use GuzzleHttp\Client;

class Recognizer extends Page implements HasForms
{
    use InteractsWithForms;

    public array $images;
    public string $attenders;

    protected $rules = [
        'images' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.recognizer';

    protected function getFormSchema(): array
    {
        return [
            FileUpload::make('images')
                ->multiple()
                ->required(),
            Textarea::make('attenders')
                ->disabled(),
        ];
    }

    public function submit()
    {
        $this->validate();

        $client = new Client([
            'base_uri' => env('RECOGNIZER_ENDPOINT', 'http://127.0.0.1:8000'),
            'timeout'  => 30.0,
        ]);

        $multipart = [];
        foreach ($this->images as $image) {
            $multipart[] = [
                'name' => 'images',
                'contents' => fopen($image->getRealPath(), 'r'),
                'filename' => $image->getClientOriginalName()
            ];
        }

        try {
            $response = $client->request('POST', 'recognize', [
                'multipart' => $multipart
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                $uniqueFaces = $responseBody['unique_faces'];
                $uniqueFacesCount = count($uniqueFaces);

                Notification::make("success")
                    ->title("Успех")
                    ->success()
                    ->body("$uniqueFacesCount faces have been recognized")
                    ->send();

                $this->attenders = implode(", ", User::whereIn('id', $uniqueFaces)->get()->map(function ($user) {
                    return $user->name . ' (#' . $user->id . ")";
                })->toArray());
            } else {
                logger()->error('API request failed', [
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);

                Notification::make("danger")
                    ->title("Ошибка")
                    ->danger()
                    ->body("Recognizing had been failed")
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
                ->body("Recognizing had been failed: " . $exception->getMessage())
                ->send();
        }
    }
}
