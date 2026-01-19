<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramChatResource\Pages;
use FieldTechVN\TelegramBackup\Models\TelegramBot;
use FieldTechVN\TelegramBackup\Models\TelegramChat;
use FieldTechVN\TelegramBackup\Services\TelegramService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelegramChatResource extends Resource
{
    protected static ?string $model = TelegramChat::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Telegram';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Telegram Chats';
    }

    public static function getPluralLabel(): string
    {
        return 'Telegram Chats';
    }

    public static function getLabel(): string
    {
        return 'Telegram Chat';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bots')
                    ->relationship('bots', 'bot_username', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true))
                    ->label('Bot')
                    ->searchable()
                    ->preload()
                    ->helperText('Select a bot to fetch chat information (optional)')
                    ->dehydrated(false),
                Forms\Components\TextInput::make('chat_id')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('Chat ID')
                    ->helperText('Telegram chat ID (user ID, group ID, or channel username). Enter chat ID and select a bot to auto-fill information.')
                    ->maxLength(255)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state && $get('bot_for_fetch')) {
                            self::fetchChatInfo($get('bot_for_fetch'), $state, $set);
                        }
                    }),
                Forms\Components\Select::make('chat_type')
                    ->label('Chat Type')
                    ->options([
                        'private' => 'Private',
                        'group' => 'Group',
                        'supergroup' => 'Supergroup',
                        'channel' => 'Channel',
                    ])
                    ->default('private')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->maxLength(255),
                Forms\Components\TextInput::make('first_name')
                    ->label('First Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->label('Last Name')
                    ->maxLength(255),
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
            ]);
    }

    /**
     * Fetch chat information from Telegram API
     */
    protected static function fetchChatInfo($botId, $chatId, callable $set): void
    {
        if (empty($botId) || empty($chatId)) {
            return;
        }

        try {
            $bot = TelegramBot::find($botId);
            if (! $bot) {
                return;
            }

            $service = app(TelegramService::class);
            $chatInfo = $service->getChatInfo($bot->bot_token, $chatId);

            if ($chatInfo) {
                $set('chat_type', $chatInfo['type'] ?? 'private');
                $set('name', $chatInfo['title'] ?? null);
                $set('username', $chatInfo['username'] ?? null);
                $set('first_name', $chatInfo['first_name'] ?? null);
                $set('last_name', $chatInfo['last_name'] ?? null);
                $set('description', $chatInfo['description'] ?? null);

                Notification::make()
                    ->title('Chat information fetched successfully!')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to fetch chat information')
                    ->body('Please verify the chat ID and ensure the bot has access to this chat.')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error fetching chat information')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('chat_id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('chat_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'private' => 'info',
                        'group' => 'success',
                        'supergroup' => 'warning',
                        'channel' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bots.bot_username')
                    ->label('Bots')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onIcon('heroicon-o-check')
                    ->offIcon('heroicon-o-no-symbol')
                    ->onColor('success')
                    ->offColor('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('chat_type')
                    ->options([
                        'private' => 'Private',
                        'group' => 'Group',
                        'supergroup' => 'Supergroup',
                        'channel' => 'Channel',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (TelegramChat $record) => $record->bots()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($records) => $records && $records->every(fn (TelegramChat $record) => $record->bots()->count() === 0)),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Chat Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('chat_id')
                            ->label('Chat ID')
                            ->copyable()
                            ->copyMessage('Chat ID copied!'),
                        Infolists\Components\TextEntry::make('chat_type')
                            ->label('Chat Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'private' => 'info',
                                'group' => 'success',
                                'supergroup' => 'warning',
                                'channel' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('username')
                            ->label('Username')
                            ->default('N/A')
                            ->formatStateUsing(fn ($state) => $state ? '@' . $state : 'N/A'),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Active Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                            ->color(fn ($state) => $state ? 'success' : 'danger'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Personal Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('first_name')
                            ->label('First Name')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('last_name')
                            ->label('Last Name')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->default('N/A')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->chat_type === 'private' || ! empty($record->first_name) || ! empty($record->last_name) || ! empty($record->description)),
                Infolists\Components\Section::make('Associated Bots')
                    ->schema([
                        Infolists\Components\TextEntry::make('bots.bot_username')
                            ->label('Bots')
                            ->badge()
                            ->separator(',')
                            ->default('No bots associated'),
                    ])
                    ->columns(1),
                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime()
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime()
                            ->default('N/A'),
                    ])
                    ->columns(2)
                    ->collapsible(),
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
            'index' => Pages\ListTelegramChats::route('/'),
            // 'view' => Pages\ViewTelegramChat::route('/{record}'),
            // 'create' => Pages\CreateTelegramChat::route('/create'),
            // 'edit' => Pages\EditTelegramChat::route('/{record}/edit'),
        ];
    }
}
