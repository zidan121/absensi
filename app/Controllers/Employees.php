<?php

namespace App\Controllers;

use App\Models\EmployeeModel;

class Employees extends BaseController
{
    public function index()
    {
        return view('employees/index', [
            'title' => 'Data Pegawai',
            'employees' => (new EmployeeModel())->orderBy('full_name')->findAll(),
        ]);
    }

    public function create()
    {
        return view('employees/create', ['title' => 'Tambah Pegawai']);
    }

    public function store()
    {
        $rules = [
            'employee_code' => 'required|max_length[40]',
            'full_name' => 'required|max_length[150]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new EmployeeModel())->insert([
            'employee_code' => trim($this->request->getPost('employee_code')),
            'full_name' => trim($this->request->getPost('full_name')),
            'position' => trim((string) $this->request->getPost('position')),
            'is_active' => 1,
        ]);

        return redirect()->to('/pegawai')->with('success', 'Pegawai berhasil ditambahkan.');
    }
}