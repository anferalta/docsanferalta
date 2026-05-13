public function edit(int $id): void
{
    echo $this->twig->render('utilizadores/form.twig', [
        'acao'         => 'editar',
        'user'         => $this->user->find($id),
        'flash'        => $this->sess->flash(),
        'usuario_nome' => $_SESSION['usuario_nome'] ?? null
    ]);
}

public function update(int $id): void
{
    $this->user->update($id, $_POST);
    $this->sess->setFlash("Utilizador atualizado!", "success");
    header("Location: /utilizadores");
    exit;
}

public function delete(int $id): void
{
    $this->user->delete($id);
    $this->sess->setFlash("Utilizador removido!", "danger");
    header("Location: /utilizadores");
    exit;
}

public function store(): void
{
    $validator = new Validator();

    $validator->required('nome', $_POST['nome'], 'O nome é obrigatório.');
    $validator->required('email', $_POST['email'], 'O email é obrigatório.');
    $validator->email('email', $_POST['email'], 'O email é inválido.');
    $validator->required('senha', $_POST['senha'], 'A senha é obrigatória.');
    $validator->min('senha', $_POST['senha'], 6, 'A senha deve ter pelo menos 6 caracteres.');

    if ($validator->hasErrors()) {
        $_SESSION['form_errors'] = $validator->getErrors();
        $_SESSION['form_old'] = $_POST;
        header("Location: /utilizadores/criar");
        exit;
    }

    $this->user->create($_POST);
    $this->sess->setFlash("Utilizador criado com sucesso!", "success");
    header("Location: /utilizadores");
    exit;
}

public function update(int $id): void
{
    $validator = new Validator();

    $validator->required('nome', $_POST['nome'], 'O nome é obrigatório.');
    $validator->required('email', $_POST['email'], 'O email é obrigatório.');
    $validator->email('email', $_POST['email'], 'O email é inválido.');

    if ($validator->hasErrors()) {
        $_SESSION['form_errors'] = $validator->getErrors();
        $_SESSION['form_old'] = $_POST;
        header("Location: /utilizadores/editar/$id");
        exit;
    }

    $this->user->update($id, $_POST);
    $this->sess->setFlash("Utilizador atualizado!", "success");
    header("Location: /utilizadores");
    exit;
}

public function index(): void
{
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total = $this->user->count();
    $pages = ceil($total / $limit);

    echo $this->twig->render('utilizadores/list.twig', [
        'utilizadores' => $this->user->paginate($limit, $offset),
        'page'         => $page,
        'pages'        => $pages,
        'flash'        => $this->sess->flash(),
        'usuario_nome' => $_SESSION['usuario_nome'] ?? null
    ]);
}