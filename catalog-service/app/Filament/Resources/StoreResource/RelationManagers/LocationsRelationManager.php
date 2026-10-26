<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    protected static ?string $title = 'Точки самовывоза';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Название точки'),
                Forms\Components\TextInput::make('city')
                    ->default('Минск')
                    ->required()
                    ->maxLength(255)
                    ->label('Город'),
                Forms\Components\TextInput::make('address')
                    ->required()
                    ->maxLength(255)
                    ->label('Адрес'),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255)
                    ->label('Телефон'),
                Forms\Components\TextInput::make('working_hours')
                    ->default('10:00 - 22:00')
                    ->required()
                    ->maxLength(255)
                    ->label('Часы работы'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required()
                    ->label('Доступна'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Точка'),
                Tables\Columns\TextColumn::make('city')
                    ->label('Город'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Адрес'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон'),
                Tables\Columns\TextColumn::make('working_hours')
                    ->label('Часы'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Доступна'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
