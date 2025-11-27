<?php
namespace App\Controllers\Public;

class PagesController {
    public function home()    { return view('Public/pages/home'); }
    public function about()   { return view('Public/pages/about'); }
    public function pricing() { return view('Public/pages/pricing'); }

    public function contact() { return view('Public/pages/contact', [
        'csrf' => csrf_token(), 'flash' => session('flash') ?? null
    ]); }

    public function contactSubmit() {
        // basic validation
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $msg   = trim($_POST['message'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $msg === '') {
            session()->flash('error', 'Please fill all fields correctly.');
            return redirect('/contact');
        }

        // send or log email (Mailer service)
        $ok = \App\Services\Mailer::send(
            to: getenv('CONTACT_TO') ?: 'info@example.com',
            subject: "[Contact] $name",
            body: "From: $name <$email>\n\n$msg"
        );

        session()->flash($ok ? 'success' : 'error',
            $ok ? 'Thanks! We will reply soon.' : 'Sorry—could not send message.');
        return redirect('/contact');
    }

    public function health()  { return json(['ok'=>true, 'time'=>date('c')]); }
    public function version() { return json(['version'=>trim(@file_get_contents(ROOT.'/VERSION')) ?: 'dev']); }
}