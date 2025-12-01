<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas;

use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;

class CrmActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Activity Details')
                    ->schema([
                        Forms\Components\Select::make('crm_entity_id')
                            ->label('Related Entity')
                            ->relationship('entity', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The entity this activity is related to'),

                        Forms\Components\Select::make('activity_type')
                            ->label('Type')
                            ->options([
                                'call' => 'Call',
                                'email' => 'Email',
                                'meeting' => 'Meeting',
                                'note' => 'Note',
                                'task' => 'Task',
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->required()
                            ->default('note')
                            ->native(false),

                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'planned' => 'Planned',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('planned')
                            ->native(false),

                        Forms\Components\Select::make('priority')
                            ->label('Priority')
                            ->options([
                                'low' => 'Low',
                                'normal' => 'Normal',
                                'high' => 'High',
                                'urgent' => 'Urgent',
                            ])
                            ->required()
                            ->default('normal')
                            ->native(false),

                        Forms\Components\Select::make('user_id')
                            ->label('Assigned To')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to assign to current user'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Schemas\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('description')
                            ->label('Description')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Schemas\Components\Section::make('Timing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label('Start Time')
                            ->native(false)
                            ->seconds(false)
                            ->helperText('When this activity starts'),

                        Forms\Components\DateTimePicker::make('end_at')
                            ->label('End Time')
                            ->native(false)
                            ->seconds(false)
                            ->after('start_at')
                            ->helperText('When this activity ends'),

                        Forms\Components\DateTimePicker::make('reminder_at')
                            ->label('Reminder')
                            ->native(false)
                            ->seconds(false)
                            ->before('start_at')
                            ->helperText('When to send reminder'),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsible(),

                Schemas\Components\Section::make('Related Items')
                    ->schema([
                        Forms\Components\Select::make('related_document_id')
                            ->label('Related Document')
                            ->relationship('document', 'document_number')
                            ->searchable()
                            ->preload()
                            ->helperText('Link to invoice/receipt/document'),

                        Forms\Components\TextInput::make('related_ticket_id')
                            ->label('Related Ticket ID')
                            ->numeric()
                            ->helperText('Link to support ticket'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
