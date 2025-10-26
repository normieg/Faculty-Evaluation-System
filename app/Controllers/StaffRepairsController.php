<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Repair;

class StaffRepairsController
{
    private Repair $repairs;

    public function __construct(?Repair $repairs = null)
    {
        $this->repairs = $repairs ?? new Repair();
    }

    public function index(): void
    {
        $repairs = $this->repairs->all();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        view('staff/index', [
            'repairs' => $repairs,
            'flash' => $flash,
        ]);
    }

    public function create(): void
    {
        $errors = $_SESSION['form_errors'] ?? [];
        $old = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        view('staff/new-repair', [
            'errors' => $errors,
            'old' => $old,
            'action' => '/repairs',
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/repairs/new');
        }

        $result = $this->repairs->create($_POST);

        if ($result['success'] === false) {
            $_SESSION['form_errors'] = $result['errors'];
            $_SESSION['form_data'] = $_POST;
            redirect('/repairs/new');
        }

        $_SESSION['flash'] = 'Repair request submitted successfully.';
        redirect('/');
    }
}
