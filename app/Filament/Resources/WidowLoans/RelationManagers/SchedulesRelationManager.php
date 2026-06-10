<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

/* -----------------------------
 | 1. LOAN SCHEDULES MANAGER
 ------------------------------*/

use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchedulesRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoan::class;
    protected static string $relationship = 'schedules';
    protected static ?string $title = 'Repayment Schedule';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('installment_number')->numeric()->required(),
            TextInput::make('amount_due')->numeric()->prefix('₦')->required(),
            DatePicker::make('due_date')->required()->native(false),
            Toggle::make('is_paid')->label('Paid Status'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('installment_number')->label('#')->alignCenter(),
                TextColumn::make('due_date')->date()->sortable(),
                TextColumn::make('amount_due')->money('NGN'),
                IconColumn::make('is_paid')->label('Status')->boolean(),
            ])
            ->defaultSort('installment_number', 'asc')
            ->headerActions([
                Action::make('regenerateSchedule')
                    ->label('Regenerate Schedule')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Repayment Schedule')
                    ->modalDescription('This will delete the existing schedule and recalculate all installments based on the current Total Payable and Disbursement Date. This action cannot be undone.')
                    ->visible(fn() => in_array($this->ownerRecord->status, [WidowLoanStatus::DISBURSED, WidowLoanStatus::COMPLETED]))
                    ->action(function () {
                        try {
                            // Ensure total_payable is set before regenerating
                            if (empty($this->ownerRecord->total_payable)) {
                                $this->ownerRecord->total_payable = $this->ownerRecord->principal_amount;
                                $this->ownerRecord->save();
                            }

                            $this->ownerRecord->generateLedger();

                            Notification::make()
                                ->success()
                                ->title('Schedule Regenerated')
                                ->body('The repayment schedule has been successfully recalculated.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Regeneration Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
            ])
            ->recordActions([
                // Only super admins can manually correct schedule entries.
                EditAction::make()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
                DeleteAction::make()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
            ]);
    }
}
