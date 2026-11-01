<?php

namespace VirtualMotors\Service;

use VirtualMotors\Repository\UserRepository;

class AuthService
{
    private UserRepository $userRepository;

    public function __construct(?UserRepository $userRepository = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public function validateCredentials(string $email, string $senha): ?array
    {
        $usuario = $this->userRepository->findByEmail($email);

        if ($usuario === null) {
            return null;
        }

        if (!password_verify($senha, $usuario['senha'])) {
            return null;
        }

        return $usuario;
    }

    public function register(
        string $nome,
        string $email,
        string $senha,
        string $tipoUsuario = 'cliente',
        string $telefone = ''
    ): ?string {
        if ($this->userRepository->emailExists($email)) {
            return null;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        return $this->userRepository->create($nome, $email, $senhaHash, $tipoUsuario, $telefone);
    }
}
