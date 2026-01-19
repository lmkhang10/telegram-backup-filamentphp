<?php

namespace FieldTechVN\TelegramBackup\Filament\Resources;

use FieldTechVN\TelegramBackup\Filament\Resources\TelegramBackupResource\Pages;
use FieldTechVN\TelegramBackup\Models\TelegramBackup;
use FieldTechVN\TelegramBackup\Services\TelegramService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class TelegramBackupResource extends Resource
{
    protected static ?string $model = TelegramBackup::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Telegram';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Telegram Backups';
    }

    public static function getPluralLabel(): string
    {
        return 'Telegram Backups';
    }

    public static function getLabel(): string
    {
        return 'Telegram Backup';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bot_id')
                    ->label('Bot')
                    ->relationship('bot', 'bot_username')
                    ->required()
                    ->disabled()
                    ->dehydrated(true),
                Forms\Components\TextInput::make('backup_name')
                    ->label('Backup Name')
                    ->disabled()
                    ->dehydrated(true),
                Forms\Components\Textarea::make('telegram_file_id')
                    ->label('Telegram File ID(s)')
                    ->disabled()
                    ->dehydrated(true)
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : ($state ?? 'N/A'))
                    ->rows(3),
                Forms\Components\Textarea::make('telegram_message_id')
                    ->label('Telegram Message ID(s)')
                    ->disabled()
                    ->dehydrated(true)
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : ($state ?? 'N/A'))
                    ->rows(3),
                Forms\Components\TextInput::make('telegram_chat_id')
                    ->label('Chat ID')
                    ->disabled()
                    ->dehydrated(true),
                Forms\Components\TextInput::make('file_size')
                    ->label('File Size')
                    ->disabled()
                    ->dehydrated(true)
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : 'N/A'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ])
                    ->disabled()
                    ->dehydrated(true),
                Forms\Components\Textarea::make('error_message')
                    ->label('Error Message')
                    ->disabled()
                    ->dehydrated(true)
                    ->visible(fn ($record) => $record && $record->status === 'failed'),
                Forms\Components\DateTimePicker::make('sent_at')
                    ->label('Sent At')
                    ->disabled()
                    ->dehydrated(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bot.bot_username')
                    ->label('Bot')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('backup_name')
                    ->label('Backup Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('telegram_message_id')
                    ->label('Message ID(s)')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'N/A';
                        }
                        if (is_array($state)) {
                            $count = count($state);
                            return $count > 1 ? $count . ' messages' : ($state[0] ?? 'N/A');
                        }
                        return $state ?? 'N/A';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('File Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bot_id')
                    ->label('Bot')
                    ->relationship('bot', 'bot_username'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(function (TelegramBackup $record) {
                        return route('telegram-backup.download', $record->id);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (TelegramBackup $record) => $record->status === 'sent' && !empty($record->telegram_file_id)),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (TelegramBackup $record) {
                        // Delete message(s) from Telegram before deleting the record
                        if ($record->bot && $record->telegram_chat_id && $record->telegram_message_id) {
                            $telegramService = app(TelegramService::class);
                            $messageIds = is_array($record->telegram_message_id) 
                                ? $record->telegram_message_id 
                                : [$record->telegram_message_id];
                            
                            $deletedCount = 0;
                            $failedCount = 0;
                            
                            foreach ($messageIds as $messageId) {
                                $result = $telegramService->deleteMessage(
                                    $record->bot->bot_token,
                                    $record->telegram_chat_id,
                                    $messageId
                                );
                                
                                if ($result['success'] ?? false) {
                                    $deletedCount++;
                                } else {
                                    $failedCount++;
                                }
                            }
                            
                            if ($deletedCount > 0) {
                                $message = $deletedCount === 1 
                                    ? 'Message deleted from Telegram'
                                    : "{$deletedCount} messages deleted from Telegram";
                                
                                if ($failedCount > 0) {
                                    $message .= " ({$failedCount} failed)";
                                }
                                
                                Notification::make()
                                    ->title($message)
                                    ->success()
                                    ->send();
                            }
                            // Continue with DB deletion even if Telegram deletion fails
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Delete messages from Telegram before deleting records
                            $telegramService = app(TelegramService::class);
                            
                            foreach ($records as $record) {
                                if ($record->bot && $record->telegram_chat_id && $record->telegram_message_id) {
                                    $messageIds = is_array($record->telegram_message_id) 
                                        ? $record->telegram_message_id 
                                        : [$record->telegram_message_id];
                                    
                                    foreach ($messageIds as $messageId) {
                                        $telegramService->deleteMessage(
                                            $record->bot->bot_token,
                                            $record->telegram_chat_id,
                                            $messageId
                                        );
                                    }
                                    // Continue with DB deletion even if Telegram deletion fails
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Backup Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('bot.bot_username')
                            ->label('Bot'),
                        Infolists\Components\TextEntry::make('backup_name')
                            ->label('Backup Name'),
                        Infolists\Components\TextEntry::make('file_size')
                            ->label('File Size')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : 'N/A'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'sent' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('sent_at')
                            ->label('Sent At')
                            ->dateTime()
                            ->default('N/A'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Telegram Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('telegram_chat_id')
                            ->label('Chat ID'),
                        Infolists\Components\TextEntry::make('telegram_message_id')
                            ->label('Message ID(s)')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'N/A';
                                }
                                if (is_array($state)) {
                                    $count = count($state);
                                    if ($count > 1) {
                                        $display = $count . ' messages (chunked)' . "\n" . implode("\n", array_slice($state, 0, 5));
                                        if ($count > 5) {
                                            $display .= "\n...";
                                        }
                                        return $display;
                                    }
                                    return $state[0] ?? 'N/A';
                                }
                                return $state ?? 'N/A';
                            })
                            ->default('N/A')
                            ->copyable()
                            ->copyMessage('Message ID(s) copied!'),
                        Infolists\Components\TextEntry::make('telegram_file_id')
                            ->label('File ID(s)')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'N/A';
                                }
                                if (is_array($state)) {
                                    $count = count($state);
                                    if ($count > 1) {
                                        $display = $count . ' files (chunked)' . "\n" . implode("\n", array_slice($state, 0, 5));
                                        if ($count > 5) {
                                            $display .= "\n...";
                                        }
                                        return $display;
                                    }
                                    return $state[0] ?? 'N/A';
                                }
                                return $state ?? 'N/A';
                            })
                            ->default('N/A')
                            ->copyable()
                            ->copyMessage('File ID(s) copied!'),
                        Infolists\Components\TextEntry::make('backup_path')
                            ->label('Backup Path')
                            ->default('N/A')
                            ->copyable()
                            ->copyMessage('Path copied!'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Error Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error Message')
                            ->default('N/A'),
                    ])
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->columns(1),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramBackups::route('/'),
            // 'view' => Pages\ViewTelegramBackup::route('/{record}'),
        ];
    }
}
