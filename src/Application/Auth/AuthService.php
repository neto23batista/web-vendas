<?php

namespace FarmaVida\Application\Auth;

use FarmaVida\Core\Security\FlashMessages;
use FarmaVida\Core\Security\SessionManager;
use FarmaVida\Infrastructure\Repository\UserRepository;
use FarmaVida\Infrastructure\Services\MailerGateway;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionManager $session,
        private readonly FlashMessages $flash,
        private readonly MailerGateway $mailer
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->session->has('id_usuario');
    }

    public function redirectPathForCurrentUser(): string
    {
        return $this->session->get('tipo') === 'dono' ? 'painel_dono.php' : 'index.php';
    }

    public function loginStatus(): array
    {
        $attempts = (int)$this->session->get('login_tentativas', 0);
        $blockedUntil = (int)$this->session->get('login_bloqueio_ate', 0);
        $blocked = $blockedUntil > time();

        return [
            'blocked' => $blocked,
            'attempts' => $attempts,
            'message' => $blocked ? 'Muitas tentativas. Tente novamente em ' . (string)ceil(($blockedUntil - time()) / 60) . ' minuto(s).' : '',
        ];
    }

    public function attemptLogin(string $email, string $password): array
    {
        $email = $this->clean($email);
        $password = (string)$password;

        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Preencha todos os campos.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Informe um e-mail válido.'];
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null && password_verify($password, (string)$user['senha'])) {
            $this->session->regenerate();
            $this->session->put('login_tentativas', 0);
            $this->session->put('login_bloqueio_ate', 0);
            $this->session->put('id_usuario', (int)$user['id']);
            $this->session->put('usuario', (string)$user['nome']);
            $this->session->put('tipo', (string)$user['tipo']);
            $this->flash->success('Bem-vindo de volta, ' . (string)$user['nome'] . '!');

            return [
                'success' => true,
                'redirect' => ((string)$user['tipo'] === 'dono') ? 'painel_dono.php' : 'index.php',
            ];
        }

        $attempts = (int)$this->session->get('login_tentativas', 0) + 1;
        if ($attempts >= 5) {
            $this->session->put('login_tentativas', 0);
            $this->session->put('login_bloqueio_ate', time() + 900);
            return ['success' => false, 'error' => 'Muitas tentativas. Conta bloqueada por 15 minutos.'];
        }

        $this->session->put('login_tentativas', $attempts);
        return ['success' => false, 'error' => 'E-mail ou senha incorretos. Restam ' . (string)(5 - $attempts) . ' tentativa(s).'];
    }

    public function register(array $input): array
    {
        $name = $this->clean((string)($input['nome'] ?? ''));
        $email = $this->clean((string)($input['email'] ?? ''));
        $password = (string)($input['senha'] ?? '');
        $confirm = (string)($input['confirmar_senha'] ?? '');
        $phone = $this->clean((string)($input['telefone'] ?? ''));
        $address = $this->clean((string)($input['endereco'] ?? ''));
        $cpfDigits = preg_replace('/\D/', '', (string)($input['cpf'] ?? ''));

        if ($name === '' || $email === '' || $password === '') {
            return ['success' => false, 'error' => 'Preencha todos os campos obrigatórios.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Informe um e-mail válido.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'A senha deve ter no mínimo 6 caracteres.'];
        }

        if ($password !== $confirm) {
            return ['success' => false, 'error' => 'As senhas não coincidem.'];
        }

        if ($cpfDigits !== '' && !$this->isValidCpf($cpfDigits)) {
            return ['success' => false, 'error' => 'CPF inválido.'];
        }

        if ($this->users->emailExists($email)) {
            return ['success' => false, 'error' => 'Este e-mail já está cadastrado.'];
        }

        $cpf = $cpfDigits !== '' ? $this->formatCpf($cpfDigits) : null;
        if ($cpf !== null && $this->users->cpfExists($cpf)) {
            return ['success' => false, 'error' => 'Este CPF já está cadastrado.'];
        }

        $this->users->createClient([
            'nome' => $name,
            'email' => $email,
            'senha' => password_hash($password, PASSWORD_DEFAULT),
            'telefone' => $phone,
            'endereco' => $address,
            'cpf' => $cpf,
        ]);

        $this->mailer->sendWelcome($email, $name);
        $this->flash->success('Conta criada com sucesso. Faça login para continuar.');

        return ['success' => true, 'redirect' => 'login.php'];
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    private function clean(string $value): string
    {
        return trim(filter_var($value, FILTER_UNSAFE_RAW));
    }

    private function formatCpf(string $cpf): string
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int)$cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int)$cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
