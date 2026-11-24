<?php

namespace App\Controllers;

use App\Models\UserModel;

class AccountSettings extends BaseController
{
    public function index()
    {
        $userId = (int) session()->get('user_id');
        $user = (new UserModel())->find($userId);

        if (! $user) {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Akun tidak ditemukan. Silakan masuk kembali.');
        }

        return view('account_settings/index', [
            'title' => 'Pengaturan Akun',
            'user' => $user,
        ]);
    }

    public function update()
    {
        $userId = (int) session()->get('user_id');
        $model = new UserModel();
        $user = $model->find($userId);

        if (! $user) {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Akun tidak ditemukan. Silakan masuk kembali.');
        }

        $fullName = trim((string) $this->request->getPost('full_name'));
        $username = trim((string) $this->request->getPost('username'));
        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        if ($fullName === '' || mb_strlen($fullName) > 150) {
            return redirect()->back()->withInput()->with('error', 'Nama lengkap wajib diisi dan maksimal 150 karakter.');
        }

        if (! preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) {
            return redirect()->back()->withInput()->with('error', 'Username harus 3–80 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        }

        $existing = $model->where('username', $username)->first();
        if ($existing && (int) $existing['id'] !== $userId) {
            return redirect()->back()->withInput()->with('error', 'Username tersebut sudah digunakan akun lain.');
        }

        $changes = [
            'full_name' => $fullName,
            'username' => $username,
        ];

        if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
            if (! password_verify($currentPassword, $user['password_hash'])) {
                return redirect()->back()->withInput()->with('error', 'Password saat ini tidak benar. Profil belum disimpan.');
            }

            if (strlen($newPassword) < 8) {
                return redirect()->back()->withInput()->with('error', 'Password baru minimal 8 karakter.');
            }

            if ($newPassword !== $confirmPassword) {
                return redirect()->back()->withInput()->with('error', 'Konfirmasi password baru tidak sama.');
            }

            $changes['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (! $model->update($userId, $changes)) {
            return redirect()->back()->withInput()->with('error', 'Pengaturan akun gagal disimpan. Periksa kembali data yang dimasukkan.');
        }

        session()->set([
            'username' => $username,
            'full_name' => $fullName,
        ]);

        return redirect()->to('/akun')->with('success', 'Pengaturan akun berhasil diperbarui.');
    }
}
