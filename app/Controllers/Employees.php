<?php

namespace App\Controllers;

use App\\Models\EmployeeModel;

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
        return view('employees/create', ['title' => 'Tambah Pegawai', 'units' => self::UNITS]);
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

    public function edit($id)
    {
        $employee = (new EmployeeModel())->find((int) $id);
        if (! $employee) {
            return redirect()->to('/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        }

        return view('employees/edit', [
            'title' => 'Edit Data Pegawai',
            'employee' => $employee,
            'units' => self::UNITS,
            'returnUnit' => (string) ($this->request->getGet('unit') ?? 'ALL'),
        ]);
    }

    public function update($id)
    {
        $model = new EmployeeModel();
        $employee = $model->find((int) $id);
        if (! $employee) {
            return redirect()->to('/pegawai')->with('error', 'Data pegawai tidak ditemukan.');
        }

        $employeeCode = trim((string) $this->request->getPost('employee_code'));
        $fullName = trim((string) $this->request->getPost('full_name'));
        $unitCode = strtoupper(trim((string) $this->request->getPost('unit_code')));
        $allowedUnits = array_keys(self::UNITS);

        if ($employeeCode === '' || strlen($employeeCode) > 40 || $fullName === '' || strlen($fullName) > 150 || ! in_array($unitCode, $allowedUnits, true)) {
            return redirect()->back()->withInput()->with('error', 'Kode, nama, atau unit pegawai tidak valid.');
        }

        $duplicate = $model->where('employee_code', $employeeCode)->where('id !=', (int) $id)->first();
        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Kode pegawai sudah digunakan oleh pegawai lain.');
        }

        $start = trim((string) $this->request->getPost('employment_start'));
        $end = trim((string) $this->request->getPost('employment_end'));
        foreach (['Tanggal mulai kerja' => $start, 'Tanggal berhenti' => $end] as $label => $date) {
            if ($date !== '' && (! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) || ! strtotime($date) || date('Y-m-d', strtotime($date)) !== $date)) {
                return redirect()->back()->withInput()->with('error', $label . ' tidak valid.');
            }
        }
        if ($start !== '' && $end !== '' && $end < $start) {
            return redirect()->back()->withInput()->with('error', 'Tanggal berhenti tidak boleh sebelum tanggal mulai kerja.');
        }

        $model->update((int) $id, [
            'employee_code' => $employeeCode,
            'full_name' => $fullName,
            'unit_code' => $unitCode,
            'position' => trim((string) $this->request->getPost('position')),
            'is_active' => $this->request->getPost('is_active') === '1' ? 1 : 0,
            'employment_start' => $start !== '' ? $start : null,
            'employment_end' => $end !== '' ? $end : null,
        ]);

        $returnUnit = strtoupper(trim((string) $this->request->getPost('return_unit')));
        $redirectUnit = $returnUnit === 'ALL' || $returnUnit === 'UNASSIGNED' || in_array($returnUnit, $allowedUnits, true) ? $returnUnit : $unitCode;
        $url = $redirectUnit === 'ALL' ? '/pegawai' : '/pegawai?unit=' . urlencode($redirectUnit);

        return redirect()->to($url)->with('success', 'Data pegawai berhasil diperbarui.');
    }
}
