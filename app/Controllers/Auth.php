<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to('/');
        }

        return view('auth/login', ['title' => 'Masuk NEURO ABSENSI']);
    }

    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())->where('username', $username)->first();
        if (! $user || empty($user['is_active']) || ! password_verify($password, $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Username atau password tidak sesuai.');
        }

        session()->regenerate();
        session()->set([
            'logged_in' => true,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
        ]);

        return redirect()->to('/');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}