<?php
namespace App\Controllers\Tenant;

class TicketController
{
    private string $dbPath;

    public function __construct()
    {
        $storageDir = dirname(__DIR__, 3) . '/apps/storage';
        if (!is_dir($storageDir)) { @mkdir($storageDir, 0775, true); }
        $this->dbPath = $storageDir . '/tickets.sqlite';
        $this->initDb();
    }

    private function pdo(): \PDO
    {
        $pdo = new \PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function initDb(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS tickets (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            module TEXT,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'Open',
            created_at TEXT NOT NULL
          )
        ");
    }

    private function json($code, $payload): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    private function genId(): string
    {
        $d = date('Ymd');
        $r = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        return "KF-{$d}-{$r}";
    }

    public function create(): void
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) $this->json(400, ['ok'=>false,'error'=>'Invalid JSON']);

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $module = trim($data['module'] ?? '');
        $subject = trim($data['subject'] ?? '');
        $body = trim($data['body'] ?? '');

        if (!$name || !$email || !$subject || !$body) {
            $this->json(422, ['ok'=>false,'error'=>'Missing required fields']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email) || strpos($email, ',') !== false) {
            $this->json(400, ['ok'=>false,'error'=>'Invalid email']);
        }

        $id = $this->genId();
        $created = date('Y-m-d H:i:s');

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("INSERT INTO tickets (id,name,email,phone,module,subject,body,status,created_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$id,$name,$email,$phone,$module,$subject,$body,'Open',$created]);

        // Send emails
        require_once dirname(__DIR__, 3) . '/apps/Public/PHP Email/ticket_mail.php';
        try {
            sendTicketEmails([
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'module'=> $module,
                'subject'=>$subject,
                'body' => $body,
                'created_at'=>$created
            ]);
        } catch (\Throwable $e) {
            // Continue even if email fails
        }

        $this->json(200, ['ok'=>true, 'ticketId'=>$id]);
    }

    public function show(string $id): void
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT id, subject, status, created_at FROM tickets WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) $this->json(404, ['ok'=>false,'error'=>'Ticket not found']);
        $this->json(200, ['ok'=>true,'ticket'=>$row]);
    }
}