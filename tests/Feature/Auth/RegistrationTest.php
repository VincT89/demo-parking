<?php

test('public registration is unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Uninvited', 'email' => 'uninvited@example.test',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'uninvited@example.test']);
});
