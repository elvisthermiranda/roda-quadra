<?php

test('guests can open the public home page', function () {
    $response = $this->get('/');

    $response->assertOk()->assertSee('Menos espera.')->assertSee('Vôlei, futsal e futebol');
});
