<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\Sessao;
use App\Models\Permissao;
use App\Services\PerfisService;
use DomainException;
use InvalidArgumentException;
use PDOException;

class PerfisAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.perfis.ver');
        $this->render('@admin/perfis/index.twig', ['perfis' => (new PerfisService())->listar()]);
    }

    public function criar()
    {
        $this->authorize('admin.perfis.criar');
        $this->formulario();
    }

    public function criarSubmit()
    {
        $this->authorize('admin.perfis.criar');
        try {
            (new PerfisService())->criar($_POST, Auth::user()->id);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->formulario(null, $e->getMessage());
            return;
        } catch (PDOException $e) {
            $this->falhaPersistencia('/admin/perfis/criar');
            return;
        }
        Sessao::flash('sucesso', 'Perfil criado com sucesso.');
        $this->redirect('/admin/perfis');
    }

    public function editar($id)
    {
        $this->authorize('admin.perfis.editar');
        $perfil = $this->perfilAlteravel($id);
        if ($perfil !== null) {
            $this->formulario($perfil);
        }
    }

    public function editarSubmit($id)
    {
        $this->authorize('admin.perfis.editar');
        $perfil = $this->perfilAlteravel($id);
        if ($perfil === null) {
            return;
        }
        try {
            (new PerfisService())->atualizar($id, $_POST, Auth::user()->id);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->formulario($perfil, $e->getMessage());
            return;
        } catch (DomainException $e) {
            $this->erroPerfil($e->getMessage());
            return;
        } catch (PDOException $e) {
            $this->falhaPersistencia('/admin/perfis/editar/' . $perfil['id']);
            return;
        }
        Sessao::flash('sucesso', 'Perfil atualizado com sucesso.');
        $this->redirect('/admin/perfis');
    }

    public function apagar($id)
    {
        $this->authorize('admin.perfis.apagar');
        try {
            (new PerfisService())->apagar($id, Auth::user()->id);
        } catch (InvalidArgumentException | DomainException $e) {
            $this->erroPerfil($e->getMessage());
            return;
        } catch (PDOException $e) {
            $this->falhaPersistencia('/admin/perfis');
            return;
        }
        Sessao::flash('sucesso', 'Perfil apagado com sucesso.');
        $this->redirect('/admin/perfis');
    }

    public function permissoes($id)
    {
        $this->authorize('admin.perfis.permissoes');
        $perfil = $this->perfilAlteravel($id);
        if ($perfil !== null) {
            $this->formularioPermissoes($perfil);
        }
    }

    public function permissoesSubmit($id)
    {
        $this->authorize('admin.perfis.permissoes');
        $perfil = $this->perfilAlteravel($id);
        if ($perfil === null) {
            return;
        }
        try {
            (new PerfisService())->guardarPermissoes($id, $_POST['permissoes'] ?? [], Auth::user()->id);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->formularioPermissoes($perfil, $e->getMessage());
            return;
        } catch (DomainException $e) {
            $this->erroPerfil($e->getMessage());
            return;
        } catch (PDOException $e) {
            $this->falhaPersistencia('/admin/perfis/permissoes/' . $perfil['id']);
            return;
        }
        Sessao::flash('sucesso', 'Permissões atualizadas.');
        $this->redirect('/admin/perfis/permissoes/' . $perfil['id']);
    }

    private function formulario(?array $perfil = null, ?string $erro = null): void
    {
        $dados = $perfil ?? ['nome' => '', 'descricao' => ''];
        if ($erro !== null) {
            foreach (['nome', 'descricao'] as $campo) {
                $dados[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
            }
        }
        $this->render($perfil === null ? '@admin/perfis/criar.twig' : '@admin/perfis/editar.twig', [
            'perfil' => $perfil,
            'dados' => $dados,
            'perfis' => (new PerfisService())->listar(),
            'erro' => $erro,
        ]);
    }

    private function formularioPermissoes(array $perfil, ?string $erro = null): void
    {
        $this->render('@admin/perfis/permissoes.twig', [
            'perfil' => $perfil,
            'todas' => Permissao::allOrdered(),
            'atuais' => (new PerfisService())->permissoesAtuais($perfil['id']),
            'erro' => $erro,
        ]);
    }

    private function perfilAlteravel(mixed $id): ?array
    {
        try {
            return (new PerfisService())->obter($id, true);
        } catch (InvalidArgumentException | DomainException $e) {
            $this->erroPerfil($e->getMessage());
            return null;
        }
    }

    private function erroPerfil(string $mensagem): void
    {
        Sessao::flash('erro', $mensagem);
        $this->redirect('/admin/perfis');
    }

    private function falhaPersistencia(string $destino): void
    {
        Sessao::flash('erro', 'Não foi possível guardar a alteração. Os dados anteriores foram preservados. Tente novamente.');
        $this->redirect($destino);
    }
}
