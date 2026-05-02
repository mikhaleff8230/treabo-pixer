<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Marvel\Database\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Комментарии';

    protected static ?string $modelLabel = 'Комментарий';

    protected static ?string $pluralModelLabel = 'Комментарии';

    protected static ?string $navigationGroup = 'Контент';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('commentable_type')
                    ->label('Тип сущности')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('commentable_id')
                    ->label('ID сущности')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('parent_id')
                    ->label('Родительский комментарий')
                    ->relationship('parent', 'id')
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('body')
                    ->label('Текст комментария')
                    ->required()
                    ->maxLength(2000)
                    ->rows(5),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает модерации',
                        'approved' => 'Одобрен',
                        'rejected' => 'Отклонен',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commentable_type')
                    ->label('Тип')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commentable_id')
                    ->label('ID сущности')
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->label('Текст')
                    ->limit(50)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ожидает модерации',
                        'approved' => 'Одобрен',
                        'rejected' => 'Отклонен',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent_id')
                    ->label('Родитель')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает модерации',
                        'approved' => 'Одобрен',
                        'rejected' => 'Отклонен',
                    ]),
                Tables\Filters\SelectFilter::make('commentable_type')
                    ->label('Тип сущности')
                    ->options([
                        'product' => 'Товар',
                        'place' => 'Плейс',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Comment $record) {
                        $record->update(['status' => 'approved']);
                    })
                    ->visible(fn (Comment $record) => $record->status !== 'approved'),
                Tables\Actions\Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Comment $record) {
                        $record->update(['status' => 'rejected']);
                    })
                    ->visible(fn (Comment $record) => $record->status !== 'rejected'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Одобрить выбранные')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['status' => 'approved']);
                            });
                        }),
                    Tables\Actions\BulkAction::make('reject')
                        ->label('Отклонить выбранные')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['status' => 'rejected']);
                            });
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'create' => Pages\CreateComment::route('/create'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

