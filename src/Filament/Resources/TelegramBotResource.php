<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBotResource\Pages;
use FieldTechVN\TelegramBackup\Models\TelegramBot;
use FieldTechVN\TelegramBackup\Jobs\StartSimpleLongPollingJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use FieldTechVN\TelegramBackup\Services\TelegramService;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Facades\Cache;

class TelegramBotResource extends Resource
{
    protected static ?string $model = TelegramBot::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Telegram';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Telegram Bots';
    }

    public static function getPluralLabel(): string
    {
        return 'Telegram Bots';
    }

    public static function getLabel(): string
    {
        return 'Telegram Bot';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('bot_token')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('Bot Token')
                    ->helperText('Get your bot token from @BotFather on Telegram')
                    ->password()
                    ->maxLength(255),
                Forms\Components\TextInput::make('bot_username')
                    ->label('Bot Username')
                    ->disabled()
                    ->helperText('Auto-filled after testing connection'),
                Forms\Components\TextInput::make('bot_name')
                    ->label('Bot Name')
                    ->disabled()
                    ->helperText('Auto-filled after testing connection'),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->onIcon('heroicon-o-check')
                    ->offIcon('heroicon-o-no-symbol')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(true),
                Forms\Components\Section::make('Fetched Chats')
                    ->schema([
                        Forms\Components\Repeater::make('fetched_chats')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('chat_id')
                                    ->label('Chat ID')
                                    ->disabled()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->disabled()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('username')
                                    ->label('Username')
                                    ->disabled()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('chat_type')
                                    ->label('Type')
                                    ->disabled()
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->deletable(false)
                            ->addable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['chat_id'] ?? null)
                            ->default(function ($record) {
                                if ($record && $record->exists) {
                                    return $record->chats->map(function ($chat) {
                                        return [
                                            'chat_id' => $chat->chat_id,
                                            'name' => $chat->name ?? null,
                                            'username' => $chat->username ?? null,
                                            'chat_type' => $chat->chat_type ?? 'private',
                                        ];
                                    })->toArray();
                                }
                                return [];
                            })
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->exists && (empty($state) || count($state) === 0)) {
                                    $chats = $record->chats->map(function ($chat) {
                                        return [
                                            'chat_id' => $chat->chat_id,
                                            'name' => $chat->name ?? null,
                                            'username' => $chat->username ?? null,
                                            'chat_type' => $chat->chat_type ?? 'private',
                                        ];
                                    })->toArray();
                                    $component->state($chats);
                                }
                            }),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record && $record->exists && $record->chats()->count() > 0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bot_username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bot_name')
                    ->label('Name')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onIcon('heroicon-o-check')
                    ->offIcon('heroicon-o-no-symbol')
                    ->onColor('success')
                    ->offColor('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('chats_count')
                    ->label('Chats')
                    ->counts('chats'),
                Tables\Columns\TextColumn::make('backups_count')
                    ->label('Backups')
                    ->counts('backups'),
                Tables\Columns\TextColumn::make('long_polling_status')
                    ->label('Long Polling')
                    ->badge()
                    ->getStateUsing(function (TelegramBot $record) {
                        return Cache::has('telegram_long_polling:' . $record->id) ? 'Active' : 'Inactive';
                    })
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-signal' : 'heroicon-o-signal-slash'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Action::make('test_connection')
                    ->label('Test Connection')
                    ->hiddenLabel()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (TelegramBot $record) {
                        $service = app(TelegramService::class);
                        $result = $service->testConnection($record, false);
                        
                        if ($result['success']) {
                            $message = 'Connection successful!';
                            
                            Notification::make()
                                ->title('Connection successful!')
                                ->body($message)
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connection failed!')
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Test Connection')
                    ->modalDescription('This will test the bot connection.'),
                Action::make('start_long_polling')
                    ->label('Start Long Polling')
                    ->hiddenLabel()
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(function (TelegramBot $record) {
                        return !Cache::has('telegram_long_polling:' . $record->id);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Start Long Polling')
                    ->modalDescription('This will dispatch a queued job that starts a short-lived long-polling session (about 1 minute) to echo back any messages sent to this bot.')
                    ->action(function (TelegramBot $record) {
                        // Dispatch queued job instead of running long polling in the HTTP request
                        StartSimpleLongPollingJob::dispatch(
                            $record->id,
                            60,   // durationSeconds
                            3000, // intervalMs
                            10    // timeoutSeconds
                        )->onQueue('default');

                        Notification::make()
                            ->title('Long polling started!')
                            ->body('The bot is now listening for messages and will echo them back. Check logs for received messages.')
                            ->success()
                            ->send();
                    }),
                Action::make('stop_long_polling')
                    ->label('Stop Long Polling')
                    ->hiddenLabel()
                    ->icon('heroicon-o-stop')
                    ->color('warning')
                    ->visible(function (TelegramBot $record) {
                        return Cache::has('telegram_long_polling:' . $record->id);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Stop Long Polling')
                    ->modalDescription('This will stop the long polling session. The job will finish its current cycle and stop.')
                    ->action(function (TelegramBot $record) {
                        // Clear cache to signal job to stop
                        Cache::forget('telegram_long_polling:' . $record->id);

                        Notification::make()
                            ->title('Long polling stopped')
                            ->body('The bot will stop listening after the current polling cycle completes.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->hiddenLabel(),
                Tables\Actions\DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (TelegramBot $record) => $record->backups()->count() === 0 && $record->chats()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($records) => $records && $records->every(fn (TelegramBot $record) => $record->backups()->count() === 0 && $record->chats()->count() === 0)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramBots::route('/'),
            'create' => Pages\CreateTelegramBot::route('/create'),
            'edit' => Pages\EditTelegramBot::route('/{record}/edit'),
        ];
    }
}
