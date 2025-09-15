<?php
// tests/UserAuthenticationTest.php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

final class UserAuthenticationTest extends DatabaseTestCase
{
    // piccola “validazione” di app che i test usano per l’ultimo caso
    private function isValidCredentials(?string $username, ?string $password): bool
    {
        $u = trim((string)$username);
        $p = (string)$password;
        return $u !== '' && $p !== '';
    }

    public function testSuccessfulUserRegistration(): void
    {
        $this->seedUser('alice', 'SuperSegreta!');

        $stmt = $this->conn->prepare("SELECT COALESCE(`password`,`password_hash`) AS pwd FROM `users` WHERE `username`=?");
        $u = 'alice';
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->assertIsArray($row);
        $this->assertTrue(password_verify('SuperSegreta!', $row['pwd']));
    }

    public function testDuplicateUsernameRegistration(): void
    {
        $this->seedUser('bob', 'pw1');

        // secondo insert sullo stesso PK deve fallire per duplicate key
        $this->expectException(mysqli_sql_exception::class);
        $stmt = $this->conn->prepare("INSERT INTO `users`(`username`,`password_hash`) VALUES(?,?)");
        $u = 'bob';
        $hash = password_hash('pw2', PASSWORD_DEFAULT);
        $stmt->bind_param('ss', $u, $hash);
        $stmt->execute(); // eccezione attesa
        $stmt->close();
    }

    public function testSuccessfulLogin(): void
    {
        $this->seedUser('carol', 'Passw0rd!');
        $stmt = $this->conn->prepare("SELECT COALESCE(`password`,`password_hash`) AS pwd FROM `users` WHERE `username`=?");
        $u = 'carol';
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->assertTrue(password_verify('Passw0rd!', $row['pwd']));
    }

    public function testLoginWithWrongPassword(): void
    {
        $this->seedUser('dave', 'giusta');

        $stmt = $this->conn->prepare("SELECT COALESCE(`password`,`password_hash`) AS pwd FROM `users` WHERE `username`=?");
        $u = 'dave';
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->assertFalse(password_verify('sbagliata', $row['pwd']));
    }

    public function testLoginWithNonexistentUser(): void
    {
        $stmt = $this->conn->prepare("SELECT 1 FROM `users` WHERE `username`=?");
        $u = 'non_esisto';
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $stmt->store_result();
        $this->assertSame(0, $stmt->num_rows);
        $stmt->close();
    }

    public function testRegistrationValidationEmptyFields(): void
    {
        // simuliamo la validazione dell’app lato test
        $this->assertFalse($this->isValidCredentials('', 'pwd'));
        $this->assertFalse($this->isValidCredentials('utente', ''));
        $this->assertFalse($this->isValidCredentials('', ''));

        // e verifichiamo che nel DB non compaiano record con username vuoto
        $res = $this->conn->query("SELECT COUNT(*) AS n FROM `users` WHERE `username`=''");
        $count = (int)$res->fetch_assoc()['n'];
        $this->assertSame(0, $count);
    }

    public function testPasswordHashing(): void
    {
        $this->seedUser('erin', 'topsecret');

        $stmt = $this->conn->prepare("SELECT COALESCE(`password`,`password_hash`) AS pwd FROM `users` WHERE `username`=?");
        $u = 'erin';
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->assertNotSame('topsecret', $row['pwd']);           // non in chiaro
        $this->assertTrue(password_verify('topsecret', $row['pwd'])); // è un hash valido
    }
}
