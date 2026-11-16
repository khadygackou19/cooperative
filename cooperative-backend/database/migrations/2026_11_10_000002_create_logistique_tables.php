<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation')->unique();
            $table->string('type')->nullable();            // camionnette, camion, charrette...
            $table->decimal('capacite_kg', 10, 2)->default(0);
            $table->boolean('disponible')->default(true);
            $table->timestamps();
        });

        Schema::create('chauffeurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('telephone')->nullable();
            $table->string('numero_permis')->nullable();
            $table->boolean('disponible')->default(true);
            $table->timestamps();
        });

        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->string('numero_bon')->unique();
            $table->foreignId('commande_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('adresse_livraison');
            $table->date('date_prevue');
            $table->date('date_livraison')->nullable();
            // planifiee | en_preparation | en_transit | livree | annulee
            $table->string('statut')->default('planifiee');
            $table->text('observation')->nullable();
            $table->timestamps();
        });

        Schema::create('collectes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete(); // entrepôt de destination
            $table->foreignId('vehicule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lieu_collecte');
            $table->date('date_prevue');
            $table->decimal('quantite_prevue', 12, 2)->default(0);
            $table->decimal('quantite_collectee', 12, 2)->nullable();
            // planifiee | en_cours | terminee | annulee
            $table->string('statut')->default('planifiee');
            $table->text('observation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collectes');
        Schema::dropIfExists('livraisons');
        Schema::dropIfExists('chauffeurs');
        Schema::dropIfExists('vehicules');
    }
};
