<?php

namespace App\\Controllers;

class RecapPositionOrders extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $saved = $db->table('recap_position_orders')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('position_name', 'ASC')
            ->get()->getResultArray();

        $known = array_map(static fn ($row) => $row['position_name'], $saved);
        $existing = $db->table('employees')
            ->select('position')
            ->where('position IS NOT NULL', null, false)
            ->where('position !=', '')
            ->groupBy('position')
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        $positions = $known;
        foreach ($existing as $row) {
            $name = trim((string) $row['position']);
            if ($name !== '' && ! in_array($name, $positions, true)) {
                $positions[] = $name;
            }
        }

        return view('recap_position_orders/index', [
            'title' => 'Urutan Jabatan Rekap',
            'positions' => $positions,
            'savedCount' => count($saved),
        ]);
    }

    public function save()
    {
        $submitted = $this->request->getPost('positions');
        if (! is_array($submitted)) {
            $submitted = [];
        }

        $positions = [];
        foreach ($submitted as $position) {
            $name = trim((string) $position);
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 100) {
                return redirect()->back()->withInput()->with('error', 'Nama jabatan maksimal 100 karakter.');
            }
            if (! in_array(mb_strtolower($name), array_map('mb_strtolower', $positions), true)) {
                $positions[] = $name;
            }
        }

        $newPosition = trim((string) $this->request->getPost('new_position'));
        if ($newPosition !== '') {
            if (mb_strlen($newPosition) > 100) {
                return redirect()->back()->withInput()->with('error', 'Nama jabatan maksimal 100 karakter.');
            }
            if (! in_array(mb_strtolower($newPosition), array_map('mb_strtolower', $positions), true)) {
                $positions[] = $newPosition;
            }
        }

        $db = db_connect();
        $db->transStart();
        $db->table('recap_position_orders')->emptyTable();
        foreach ($positions as $index => $position) {
            $db->table('recap_position_orders')->insert([
                'position_name' => $position,
                'sort_order' => $index + 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'Urutan jabatan gagal disimpan.');
        }

        return redirect()->to('/pengaturan-urutan-jabatan')
            ->with('success', 'Urutan jabatan berhasil disimpan dan akan digunakan pada ekspor REKAB.');
    }
}
