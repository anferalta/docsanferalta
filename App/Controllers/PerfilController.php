class PerfilController {
    public function index() {
        return view('@site/perfil.twig', [
            'user' => auth()->user()
        ]);
    }
}

