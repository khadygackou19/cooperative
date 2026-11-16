<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport de synthèse — Teranga Agri Coop</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2d27; }
        h1 { color: #2d5c46; font-size: 20px; margin-bottom: 2px; }
        h2 { color: #2d5c46; font-size: 14px; margin-top: 22px; border-bottom: 2px solid #2d5c46; padding-bottom: 3px; }
        .muted { color: #6b7a73; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #2d5c46; color: #fff; text-align: left; padding: 5px; }
        td { border-bottom: 1px solid #dfe8e3; padding: 5px; }
        .right { text-align: right; }
        .kpi { margin-top: 10px; font-size: 13px; }
    </style>
</head>
<body>
    <h1>Teranga Agri Coop</h1>
    <div class="muted">Rapport de synthèse — généré le {{ $date }}</div>

    <h2>Stocks</h2>
    <table>
        <tr><th>Produit</th><th>Producteur</th><th>Entrepôt</th><th class="right">Quantité</th><th>Niveau</th></tr>
        @forelse ($produits as $p)
            <tr>
                <td>{{ $p->nom_produit }}</td>
                <td>{{ trim(($p->culture?->periode?->parcelle?->membre?->user?->prenom ?? '').' '.($p->culture?->periode?->parcelle?->membre?->user?->nom ?? '')) }}</td>
                <td>{{ $p->entrepot()?->nom_stock }}</td>
                <td class="right">{{ number_format($p->quantite, 2, ',', ' ') }} {{ $p->unite }}</td>
                <td>{{ $p->niveau }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Aucun produit en stock.</td></tr>
        @endforelse
    </table>

    <h2>Ventes</h2>
    <div class="kpi"><strong>Chiffre d'affaires :</strong> {{ number_format($chiffre_affaires, 0, ',', ' ') }} FCFA — {{ $ventes->count() }} vente(s)</div>
    <table>
        <tr><th>N°</th><th>Date</th><th>Client</th><th class="right">Montant (FCFA)</th></tr>
        @forelse ($ventes as $c)
            <tr>
                <td>#{{ $c->id }}</td>
                <td>{{ $c->date_commande?->format('d/m/Y') }}</td>
                <td>{{ trim(($c->client->prenom_client ?? '').' '.$c->client->nom_client) }}</td>
                <td class="right">{{ number_format($c->montant_total, 0, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Aucune vente.</td></tr>
        @endforelse
    </table>

    <h2>Livraisons</h2>
    <table>
        <tr><th>Bon</th><th>Client</th><th>Date prévue</th><th>Véhicule</th><th>Chauffeur</th><th>Statut</th></tr>
        @forelse ($livraisons as $l)
            <tr>
                <td>{{ $l->numero_bon }}</td>
                <td>{{ trim(($l->commande?->client?->prenom_client ?? '').' '.($l->commande?->client?->nom_client ?? '')) }}</td>
                <td>{{ $l->date_prevue?->format('d/m/Y') }}</td>
                <td>{{ $l->vehicule?->immatriculation }}</td>
                <td>{{ $l->chauffeur ? trim($l->chauffeur->prenom.' '.$l->chauffeur->nom) : '' }}</td>
                <td>{{ $l->statut }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Aucune livraison.</td></tr>
        @endforelse
    </table>
</body>
</html>
