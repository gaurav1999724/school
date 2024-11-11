<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

class StaffDataExport implements FromCollection, WithTitle, WithHeadings, ShouldAutoSize, WithStrictNullComparison, WithMultipleSheets {
    protected mixed $results;
    protected Collection $formFields;

    
    public function title(): string {
        return 'Staff Registration';
    }

    public function headings(): array {
        $columns = [
            'first_name',
            'last_name',
            'mobile',
            'email',
            'dob',
            'salary',
            'joining_date'
        ];
        return $columns;
    }

    public function sheets(): array {
        $sheets = [];

        // add the main data sheet
        $sheets[] = new StaffDataExport();

        return $sheets;
    }

    private function getActionItems() {
        $fields = [
            'test',
            'example',
            '1234567899',
            'guaridan@example.com',
            date('d-m-Y'),
            '10000',
            date('d-m-Y')
        ];
      
        return $fields;
    }

    public function collection() {
        // store the results for later use
        $this->results = $this->getActionItems();

        return collect(array($this->results));
    }
}
