<?php

namespace App\Controllers;

use App\Models\\EmployeeModel;

class Employees extends BaseController
{
    private const UNITS = [
        'KBIT' => 'KBIT',
        'RAIT' => 'RAIT',
        'SDIT' => 'SDIT',
        'SMPIT' => 'SMPIT',
        'YAYASAN' => 'Yayasan',
        'RAIT_KBIT' => 'RAIT + KBIT (belum dipisah)',
    ];

    public function index()
    {
        $model = new EmployeeModel();
        $selectedUnit = strtoupper(trim((string) $this->request->getGet('unit')));
        if ($selectedUnit !== 'UNASSIGNED' && ! array_key_exists($selectedUnit, self::UNITS)) {
            $selectedUnit = 'ALL';
        }

        $counts = ['ALL' => $model->countAllResults(false)];
        foreach (self::UNITS as $code => $label) {
            $counts[$code] = $model->where('unit_code', $code)->countAllResults();
        }
        $counts['UNASSIGNED'] = $model->groupStart()
            ->where('unit_code', null)
            ->orWhere('unit_code', '')
            ->groupEnd()
            ->countAllResults();

        $query = (new EmployeeModel())->orderBy('full_name', 'ASC');
        if ($selectedUnit === 'UNASSIGNED') {
            $query->groupStart()->where('unit_code', null)->orWhere('unit_code', '')->groupEnd();
        } elseif ($selectedUnit !== 'ALL') {
            $query->where('unit_code', $selectedUnit);
        }

        return view('employees/index', [
            'title' => 'Data Pegawai',
            'employees' => $query->findAll(),
            'units' => self::UNITS,
            'selectedUnit' => $selectedUnit,
            'counts' => $counts,
        ]);
    }

    public function create()
    {
        return view('employees/create', [
            'title' => 'Tambah Pegawai',
            'units' => self::UNITS,
        ]);
    }

    public function store()
    {
        $rules = [
            'employee_code' => 'required|max_length[40]',
            'full_name' => 'required|max_length[150]',
            'unit_code' => 'required|in_list[KBIT,RAIT,SDIT,SMPIT,YAYASAN]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new EmployeeModel())->insert([
            'employee_code' => trim($this->request->getPost('employee_code')),
            'unit_code' => $this->request->getPost('unit_code'),
            'full_name' => trim($this->request->getPost('full_name')),
            'position' => trim((string) $this->request->getPost('position')),
            'is_active' => 1,
        ]);

        return redirect()->to('/pegawai?unit=' . urlencode((string) $this->request->getPost('unit_code')))
            ->with('success', 'Pegawai berhasil ditambahkan.');
    }
}
