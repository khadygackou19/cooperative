<?php

return [
    // Quantité en dessous de laquelle un produit est signalé "niveau faible".
    'seuil_stock_bas' => (float) env('COOP_SEUIL_STOCK_BAS', 50),
];
