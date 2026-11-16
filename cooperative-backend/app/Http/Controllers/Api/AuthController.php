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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** POST /login {username, password} -> {token, user} */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => ['Identifiant ou mot de passe incorrect.']]);
        }

        if ($user->role === 'membre') {
            $statut = $user->membre?->statut;
            if ($statut === 'en_attente') {
                return response()->json(['message' => "Votre demande d'adhésion est en attente de validation par un administrateur."], 403);
            }
            if ($statut !== 'actif') {
                return response()->json(['message' => "Votre demande d'adhésion n'a pas été acceptée."], 403);
            }
        }

        $token = $user->createToken('auth')->plainTextToken;
        Journal::log($user->id, 'Connexion', 'User', $user->id);

        return response()->json(['token' => $token, 'user' => Presenter::user($user)]);
    }

    /** POST /register : demande d'adhésion d'un agriculteur (reste en attente jusqu'à validation admin). */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'username' => 'required|string|max:60|unique:users,username',
            'telephone' => 'required|string|max:30',
            'localite' => 'nullable|string|max:150',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'username' => $data['username'],
                'telephone' => $data['telephone'],
                'role' => 'membre',
                'password' => $data['password'],
            ]);
            Membre::create(['user_id' => $user->id, 'statut' => 'en_attente', 'localite' => $data['localite'] ?? null]);

            return $user;
        });

        Journal::log($user->id, "Demande d'adhésion", 'User', $user->id);

        return response()->json([
            'message' => "Demande envoyée. Elle sera examinée par un administrateur.",
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(Presenter::user($request->user()));
    }

    /** PUT /me : modification du profil (et du mot de passe si fourni). */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'nom' => 'sometimes|required|string|max:100',
            'prenom' => 'sometimes|required|string|max:100',
            'telephone' => 'nullable|string|max:30',
            'ancien_password' => 'required_with:password|string',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check($data['ancien_password'], $user->password)) {
                throw ValidationException::withMessages(['ancien_password' => ['Ancien mot de passe incorrect.']]);
            }
            $user->password = $data['password'];
        }

        $user->fill(collect($data)->only(['nom', 'prenom', 'telephone'])->all())->save();
        Journal::log($user->id, 'Modification du profil', 'User', $user->id);

        return response()->json(Presenter::user($user->fresh()));
    }
}
