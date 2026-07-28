<?php

test('the registration routes no longer exist', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('the welcome page does not offer a register link', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('canRegister', false));
});
