<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Sessao;
use App\Core\Validator;
use App\Core\Auth;
use App\Models\Utilizador;
use App\Models\Perfil;
use App\Models\Audit;

class UtilizadoresController extends BaseController
{
    /**
     * Lista todos os utilizadores
     */
    public function index()
    {
        $this->authorize('admin.utilizadores.ver');

        $utilizadores = Utilizador::all();

        return $this->render('admin/utilizadores/index.twig', [
            'title'        => 'Utilizadores',
            'utilizadores' => $utilizadores,
            'user'         => Auth::user()
        ]);
    }

    /**
     * Lista utilizadores pendentes
     */
    public function pendentes()
    {
        $this->authorize('admin.utilizadores.ver');

        $pendentes = Utilizador::query()
            ->where('estado', '=', 0)
            ->get();

        return $this->render('admin/utilizadores/pendentes.twig', [
            'title'     => 'Utilizadores Pendentes',
            'pendentes' => $pendentes,
            'user'      => Auth::user()
        ]);
    }

    /**
     * Aprovar utilizador
     */
    public function aprovar($id)
    {
        $this->authorize('admin.utilizadores.editar');

        $user = Utilizador::find($id);

        if (!$user) {
            Sessao::flash('erro', 'Utilizador não encontrado.');
            return $this->redirect('/admin/utilizadores/pendentes');
        }

        $user->update(['estado' => 1], "id = :id", [':id' => $id]);

        Audit::log("Aprovou o utilizador ID $id");

        Sessao::flash('sucesso', 'Utilizador aprovado com sucesso.');
        return $this->redirect('/admin/utilizadores/pendentes');
    }

    /**
     * Rejeitar utilizador (apagar)
     */
    public function rejeitar($id)
    {
        $this->authorize('admin.utilizadores.eliminar');

        Utilizador::deleteWhere("id = :id", [':id' => $id]);

        Audit::log("Rejeitou o utilizador ID $id");

        Sessao::flash('sucesso', 'Utilizador rejeitado.');
        return $this->redirect('/admin/utilizadores/pendentes');
    }

    /**
     * Bloquear utilizador (estado = 0)
     */
    public function bloquear($id)
    {
        $this->authorize('admin.utilizadores.editar');

        $user = Utilizador::find($id);

        if ($user) {
            $user->update(['estado' => 0], "id = :id", [':id' => $id]);
            Audit::log("Bloqueou o utilizador ID $id");
        }

        Sessao::flash('sucesso', 'Utilizador bloqueado.');
        return $this->redirect('/admin/utilizadores');
    }

    /**
     * Desbloquear utilizador (estado = 1)
     */
    public function desbloquear($id)
    {
        $this->authorize('admin.utilizadores.editar');

        $user = Utilizador::find($id);

        if ($user) {
            $user->update(['estado' => 1], "id = :id", [':id' => $id]);
            Audit::log("Desbloqueou o utilizador ID $id");
        }

        Sessao::flash('sucesso', 'Utilizador desbloqueado.');
        return $this->redirect('/admin/utilizadores');
    }

    /**
     * Criar utilizador manualmente
     */
    public function criar()
    {
        $this->authorize('admin.utilizadores.criar');

        return $this->render('admin/utilizadores/criar.twig', [
            'title'  => 'Criar Utilizador',
            'perfis' => Perfil::all(),
            'user'   => Auth::user()
        ]);
    }

    public function store()
    {
        $this->authorize('admin.utilizadores.criar');

        $nome      = trim($_POST['nome'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $perfil_id = (int)($_POST['perfil_id'] ?? 0);

        $v = new Validator();
        $v->required('nome', $nome, 'O nome é obrigatório.');
        $v->required('email', $email, 'O email é obrigatório.');
        $v->required('password', $password, 'A password é obrigatória.');

        if ($v->hasErrors()) {
            Sessao::flash('erro', $v->firstError());
            return $this->redirect('/admin/utilizadores/criar');
        }

        (new Utilizador())->insert([
            'nome'      => $nome,
            'email'     => $email,
            'password'  => password_hash($password, PASSWORD_DEFAULT),
            'perfil_id' => $perfil_id,
            'estado'    => 1
        ]);

        Audit::log("Criou um novo utilizador");

        Sessao::flash('sucesso', 'Utilizador criado com sucesso.');
        return $this->redirect('/admin/utilizadores');
    }

    public function editar($id)
    {
        $this->authorize('admin.utilizadores.editar');

        return $this->render('admin/utilizadores/editar.twig', [
            'title'      => 'Editar Utilizador',
            'utilizador' => Utilizador::find($id),
            'perfis'     => Perfil::all(),
            'user'       => Auth::user()
        ]);
    }

    public function update($id)
    {
        $this->authorize('admin.utilizadores.editar');

        $nome      = trim($_POST['nome'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $perfil_id = (int)($_POST['perfil_id'] ?? 0);

        (new Utilizador())->update([
            'nome'      => $nome,
            'email'     => $email,
            'perfil_id' => $perfil_id
        ], "id = :id", [':id' => $id]);

        Audit::log("Editou o utilizador ID $id");

        Sessao::flash('sucesso', 'Utilizador atualizado.');
        return $this->redirect('/admin/utilizadores');
    }

    public function delete($id)
    {
        $this->authorize('admin.utilizadores.eliminar');

        Utilizador::deleteWhere("id = :id", [':id' => $id]);

        Audit::log("Eliminou o utilizador ID $id");

        Sessao::flash('sucesso', 'Utilizador eliminado.');
        return $this->redirect('/admin/utilizadores');
    }
}