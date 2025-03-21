<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// Force no caching for AJAX requests
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

if (isset($_GET['partial']) && $_GET['partial'] == '1') {
    try {
        $articleId = (int)($_GET['id'] ?? 0);
        if ($articleId < 1) {
            throw new Exception("ID d'article invalide");
        }

        // Fetch article details
        $article = $conn->query("
            SELECT * FROM Articles WHERE Article_id = $articleId
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            throw new Exception("Article non trouvé");
        }

        // Update the accessories query to match table structure
        $accessories = $conn->query("
            SELECT a.*, aa.quantity, c.designation as category_name,
                   COALESCE(a.poids_avec_carotte, 0) as poids_avec_carotte,
                   COALESCE(a.poids_sans_carotte, 0) as poids_sans_carotte
            FROM Accessoires a
            INNER JOIN ArticleAccessoiries aa ON a.Accessoire_id = aa.Accessoire_id
            LEFT JOIN Categories c ON a.categorie_id = c.Categorie_id
            WHERE aa.article_id = $articleId
            ORDER BY a.designation
        ")->fetchAll(PDO::FETCH_ASSOC);
?>
        <div class="container mx-auto p-6">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        Accessoires de l'article: <?= htmlspecialchars($article['designation']) ?>
                    </h2>
                    <button type="button" onclick="closeAccessories()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <?php if (count($accessories) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids avec carotte</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids sans carotte</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($accessories as $accessory): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?= htmlspecialchars($accessory['designation']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($accessory['category_name'] ?? 'Non catégorisé') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?= htmlspecialchars($accessory['quantity']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <?= number_format($accessory['poids_avec_carotte'], 2) ?> kg
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <?= number_format($accessory['poids_sans_carotte'], 2) ?> kg
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-500">
                        Aucun accessoire associé à cet article
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    } catch (Exception $e) {
        echo "<div class='text-red-500 p-4'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit();
}
?>
