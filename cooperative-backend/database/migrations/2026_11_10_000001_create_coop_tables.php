<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('statut')->default('en_attente'); // en_attente | actif | refuse
            $table->date('date_adhesion')->nullable();
            $table->string('localite')->nullable();
            $table->timestamps();
        });

        Schema::create('parcelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained()->cascadeOnDelete();
            $table->string('nom')->nullable();
            $table->decimal('superficie', 10, 2);
            $table->string('type_sol')->nullable();
            $table->timestamps();
        });

        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcelle_id')->constrained()->cascadeOnDelete();
            $table->string('type');             // ex : Hivernage, Contre-saison
            $table->string('variete')->nullable();
            $table->date('date_debut')->nullable();
            $table->timestamps();
        });

        Schema::create('cultures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->string('statut_culture')->default('en_cours'); // en_cours | recoltee
            $table->timestamps();
        });

        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culture_id')->constrained()->cascadeOnDelete();
            $table->string('nom_produit');
            $table->decimal('quantite', 12, 2);
            $table->decimal('prix_vente', 12, 2);
            $table->string('unite')->default('kg');
            $table->date('date_entree');
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('nom_stock');
            $table->string('adresse')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvement_stockages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type_mouvement'); // entree | sortie
            $table->date('date_mouvement');
            $table->decimal('quantite', 12, 2);
            $table->string('motif')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom_client');
            $table->string('prenom_client')->nullable();
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->timestamps();
        });

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // commercial
            $table->date('date_commande');
            $table->string('statut')->default('en_attente'); // en_attente | validee | refusee
            $table->timestamps();
        });

        Schema::create('ligne_commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantite', 12, 2);
            $table->decimal('prix_unitaire', 12, 2);
            $table->timestamps();
        });

        Schema::create('journal_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entite')->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'journal_actions', 'ligne_commandes', 'commandes', 'clients', 'mouvement_stockages',
            'stocks', 'produits', 'cultures', 'periodes', 'parcelles', 'membres',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
