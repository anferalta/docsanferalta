<?php
namespace App\Controllers;
// Compatibility for old links: apply the same owner checks as private documents.
class DocumentosPublicController
{
    public function abrirPublico($id): void
    {
        (new DocumentosUserController())->abrir($id);
    }
}
