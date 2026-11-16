<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membre;
use App\Models\User;
use App\Support\Journal;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Gestion des utilisateurs et des demandes d'adhésion (admin). */
class UtilisateurController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::with('membre')->orderByDesc('id');

        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }
        if ($statut = $request->query('statut')) {
            $q->whereHas('membre', fn ($m) => $m->where('statut', $statut));
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('nom', 'like', "%$s%")->orWhere('prenom', 'like', "%$s%")->orWhere('username', 'like', "%$s%"));
        }

        return response()->json($q->get()->map(fn ($u) => Presenter::user($u))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'username' => 'required|string|max:60|unique:users,username',
            'telephone' => 'nullable|string|max:30',
            'matricule' => 'nullable|string|max:50',
            'role' => ['required', Rule::in(['admin', 'commercial', 'magasinier', 'membre'])],
            'localite' => 'nullable|string|max:150',
            'password' => 'required|string|min:6',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except('localite')->all());
            if ($user->role === 'membre') {
                Membre::create([
                    'user_id' => $user->id,
                    'statut' => 'actif',
                    'date_adhesion' => now()->toDateString(),
                    'localite' => $data['localite'] ?? null,
                ]);
            }

            return $user;
        });

        Journal::log($request->user()->id, 'Création d\'utilisateur', 'User', $user->id, $user->username.' ('.$user->role.')');

        return response()->json(Presenter::user($user), 201);
    }

    public function update(Request $request, User $utilisateur): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|required|string|max:100',
            'prenom' => 'sometimes|required|string|max:100',
            'username' => ['sometimes', 'required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($utilisateur->id)],
            'telephone' => 'nullable|string|max:30',
            'matricule' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $utilisateur->update($data);
        Journal::log($request->user()->id, "Modification d'utilisateur", 'User', $utilisateur->id);

        return response()->json(Presenter::user($utilisateur->fresh()));
    }

    public function destroy(Request $request, User $utilisateur): JsonResponse
    {
        if ($utilisateur->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        Journal::log($request->user()->id, "Suppression d'utilisateur", 'User', $utilisateur->id, $utilisateur->username);
        $utilisateur->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /** POST /admin/membres/{user}/valider */
    public function valider(Request $request, User $utilisateur): JsonResponse
    {
        return $this->changerStatut($request, $utilisateur, 'actif');
    }

    /** POST /admin/membres/{user}/refuser */
    public function refuser(Request $request, User $utilisateur): JsonResponse
    {
        return $this->changerStatut($request, $utilisateur, 'refuse');
    }

    private function changerStatut(Request $request, User $utilisateur, string $statut): JsonResponse
    {
        $membre = $utilisateur->membre;
        abort_unless($membre, 404, "Cet utilisateur n'est pas un membre.");

        $membre->update([
            'statut' => $statut,
            'date_adhesion' => $statut === 'actif' ? now()->toDateString() : $membre->date_adhesion,
        ]);

        Journal::log($request->user()->id, $statut === 'actif' ? 'Adhésion validée' : 'Adhésion refusée', 'User', $utilisateur->id, $utilisateur->username);

        return response()->json(Presenter::user($utilisateur->fresh()));
    }
}
