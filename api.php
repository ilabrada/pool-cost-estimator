<?php
/**
 * API Endpoint - Handles AJAX requests
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Search Clients (for autocomplete) ───────────────────────────
    case 'search_clients':
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 1) {
            echo json_encode([]);
            exit;
        }
        $clients = getClients($query, 10);
        echo json_encode($clients);
        break;

    // ── List pool shape images ─────────────────────────────────────
    case 'list_pool_images':
        $shapeImages = [];
        $shapes = ['rectangular', 'l-shaped', 'kidney', 'oval', 'freeform'];
        $basePath = __DIR__ . '/assets/img/pool-shapes/';

        foreach ($shapes as $shape) {
            $shapePath = $basePath . $shape . '/';
            $images = [];

            if (is_dir($shapePath)) {
                $files = scandir($shapePath);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $file)) {
                        $images[] = 'assets/img/pool-shapes/' . $shape . '/' . $file;
                    }
                }
            }

            $shapeImages[$shape] = $images;
        }

        echo json_encode($shapeImages);
        break;

    // ── Get single client ───────────────────────────────────────────
    case 'get_client':
        $id = (int)($_GET['id'] ?? 0);
        $client = getClient($id);
        echo json_encode($client ?: ['error' => 'Not found']);
        break;

    // ── Calculate estimate costs ────────────────────────────────────
    case 'calculate':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }

        $pricing = getPricingByKey();
        $metrics = calculatePoolMetrics($data);
        $items = [];
        $sortOrder = 0;

        $length = (float)($data['pool_length'] ?? 0);
        $width = (float)($data['pool_width'] ?? 0);

        if ($length <= 0 || $width <= 0) {
            echo json_encode(['items' => [], 'metrics' => $metrics, 'subtotal' => 0]);
            exit;
        }

        // Excavation
        if ($metrics['volume_cuft'] > 0 && isset($pricing['excavation'])) {
            $items[] = [
                'category' => 'excavation',
                'description' => $pricing['excavation']['item_label'],
                'quantity' => $metrics['volume_cuft'],
                'unit' => 'cu ft',
                'unit_price' => (float)$pricing['excavation']['unit_price'],
                'total' => round($metrics['volume_cuft'] * (float)$pricing['excavation']['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        if ($metrics['volume_cuft'] > 0 && isset($pricing['hauling'])) {
            $items[] = [
                'category' => 'excavation',
                'description' => $pricing['hauling']['item_label'],
                'quantity' => $metrics['volume_cuft'],
                'unit' => 'cu ft',
                'unit_price' => (float)$pricing['hauling']['unit_price'],
                'total' => round($metrics['volume_cuft'] * (float)$pricing['hauling']['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        // Pool Shell
        $materialKey = 'shell_' . ($data['pool_material'] ?? 'concrete');
        if ($metrics['surface_area'] > 0 && isset($pricing[$materialKey])) {
            $items[] = [
                'category' => 'shell',
                'description' => $pricing[$materialKey]['item_label'],
                'quantity' => $metrics['surface_area'],
                'unit' => 'sq ft',
                'unit_price' => (float)$pricing[$materialKey]['unit_price'],
                'total' => round($metrics['surface_area'] * (float)$pricing[$materialKey]['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        // Interior Finish (only for concrete pools)
        if (($data['pool_material'] ?? 'concrete') === 'concrete') {
            $finishKey = 'finish_' . ($data['interior_finish'] ?? 'plaster');
            if ($metrics['surface_area'] > 0 && isset($pricing[$finishKey])) {
                $items[] = [
                    'category' => 'finish',
                    'description' => $pricing[$finishKey]['item_label'],
                    'quantity' => $metrics['surface_area'],
                    'unit' => 'sq ft',
                    'unit_price' => (float)$pricing[$finishKey]['unit_price'],
                    'total' => round($metrics['surface_area'] * (float)$pricing[$finishKey]['unit_price'], 2),
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        // Equipment (flat rate items)
        $equipmentItems = ['plumbing', 'electrical', 'filtration', 'equipment_pad'];
        foreach ($equipmentItems as $eqKey) {
            if (isset($pricing[$eqKey])) {
                $items[] = [
                    'category' => 'equipment',
                    'description' => $pricing[$eqKey]['item_label'],
                    'quantity' => 1,
                    'unit' => 'flat',
                    'unit_price' => (float)$pricing[$eqKey]['unit_price'],
                    'total' => (float)$pricing[$eqKey]['unit_price'],
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        // Tile & Coping
        if ($metrics['perimeter'] > 0 && isset($pricing['coping'])) {
            $items[] = [
                'category' => 'tile',
                'description' => $pricing['coping']['item_label'],
                'quantity' => $metrics['perimeter'],
                'unit' => 'lin ft',
                'unit_price' => (float)$pricing['coping']['unit_price'],
                'total' => round($metrics['perimeter'] * (float)$pricing['coping']['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        if ($metrics['perimeter'] > 0 && isset($pricing['waterline_tile'])) {
            $items[] = [
                'category' => 'tile',
                'description' => $pricing['waterline_tile']['item_label'],
                'quantity' => $metrics['perimeter'],
                'unit' => 'lin ft',
                'unit_price' => (float)$pricing['waterline_tile']['unit_price'],
                'total' => round($metrics['perimeter'] * (float)$pricing['waterline_tile']['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        // Features
        if (!empty($data['has_jacuzzi'])) {
            $jacuzziKey = 'jacuzzi_' . ($data['jacuzzi_size'] ?? 'standard');
            if (isset($pricing[$jacuzziKey])) {
                $items[] = [
                    'category' => 'features',
                    'description' => $pricing[$jacuzziKey]['item_label'],
                    'quantity' => 1,
                    'unit' => 'flat',
                    'unit_price' => (float)$pricing[$jacuzziKey]['unit_price'],
                    'total' => (float)$pricing[$jacuzziKey]['unit_price'],
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        $numLights = (int)($data['num_lights'] ?? 0);
        if ($numLights > 0 && isset($pricing['led_light'])) {
            $items[] = [
                'category' => 'features',
                'description' => $pricing['led_light']['item_label'],
                'quantity' => $numLights,
                'unit' => 'each',
                'unit_price' => (float)$pricing['led_light']['unit_price'],
                'total' => round($numLights * (float)$pricing['led_light']['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        if (!empty($data['has_heating'])) {
            $heatingKey = 'heater_' . ($data['heating_type'] ?? 'gas');
            if (isset($pricing[$heatingKey])) {
                $items[] = [
                    'category' => 'features',
                    'description' => $pricing[$heatingKey]['item_label'],
                    'quantity' => 1,
                    'unit' => 'flat',
                    'unit_price' => (float)$pricing[$heatingKey]['unit_price'],
                    'total' => (float)$pricing[$heatingKey]['unit_price'],
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        if (!empty($data['has_waterfall']) && isset($pricing['waterfall'])) {
            $items[] = [
                'category' => 'features',
                'description' => $pricing['waterfall']['item_label'],
                'quantity' => 1,
                'unit' => 'flat',
                'unit_price' => (float)$pricing['waterfall']['unit_price'],
                'total' => (float)$pricing['waterfall']['unit_price'],
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        if (!empty($data['has_water_feature']) && isset($pricing['water_feature'])) {
            $items[] = [
                'category' => 'features',
                'description' => $pricing['water_feature']['item_label'],
                'quantity' => 1,
                'unit' => 'flat',
                'unit_price' => (float)$pricing['water_feature']['unit_price'],
                'total' => (float)$pricing['water_feature']['unit_price'],
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        if (!empty($data['has_auto_cover']) && isset($pricing['auto_cover'])) {
            $floorArea = $metrics['floor_area'];
            $items[] = [
                'category' => 'features',
                'description' => $pricing['auto_cover']['item_label'],
                'quantity' => $floorArea,
                'unit' => 'sq ft',
                'unit_price' => (float)$pricing['auto_cover']['unit_price'],
                'total' => round($floorArea * (float)$pricing['auto_cover']['unit_price'], 2),
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        if (!empty($data['has_pool_cleaner']) && isset($pricing['pool_cleaner'])) {
            $items[] = [
                'category' => 'features',
                'description' => $pricing['pool_cleaner']['item_label'],
                'quantity' => 1,
                'unit' => 'flat',
                'unit_price' => (float)$pricing['pool_cleaner']['unit_price'],
                'total' => (float)$pricing['pool_cleaner']['unit_price'],
                'sort_order' => $sortOrder++,
                'is_custom' => 0,
            ];
        }

        // Deck
        if (!empty($data['has_deck'])) {
            $deckKey = 'deck_' . ($data['deck_material'] ?? 'concrete');
            $deckArea = (float)($data['deck_area'] ?? 0);
            if ($deckArea > 0 && isset($pricing[$deckKey])) {
                $items[] = [
                    'category' => 'deck',
                    'description' => $pricing[$deckKey]['item_label'],
                    'quantity' => $deckArea,
                    'unit' => 'sq ft',
                    'unit_price' => (float)$pricing[$deckKey]['unit_price'],
                    'total' => round($deckArea * (float)$pricing[$deckKey]['unit_price'], 2),
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        // Fence
        if (!empty($data['has_fence'])) {
            $fenceKey = 'fence_' . ($data['fence_type'] ?? 'aluminum');
            $fenceLength = (float)($data['fence_length'] ?? 0);
            if ($fenceLength > 0 && isset($pricing[$fenceKey])) {
                $items[] = [
                    'category' => 'fence',
                    'description' => $pricing[$fenceKey]['item_label'],
                    'quantity' => $fenceLength,
                    'unit' => 'lin ft',
                    'unit_price' => (float)$pricing[$fenceKey]['unit_price'],
                    'total' => round($fenceLength * (float)$pricing[$fenceKey]['unit_price'], 2),
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        // Other (permits, engineering, startup)
        $otherItems = ['permits', 'engineering', 'startup'];
        foreach ($otherItems as $otherKey) {
            if (isset($pricing[$otherKey])) {
                $items[] = [
                    'category' => 'other',
                    'description' => $pricing[$otherKey]['item_label'],
                    'quantity' => 1,
                    'unit' => 'flat',
                    'unit_price' => (float)$pricing[$otherKey]['unit_price'],
                    'total' => (float)$pricing[$otherKey]['unit_price'],
                    'sort_order' => $sortOrder++,
                    'is_custom' => 0,
                ];
            }
        }

        // Calculate subtotal
        $subtotal = array_sum(array_column($items, 'total'));

        echo json_encode([
            'items' => $items,
            'metrics' => $metrics,
            'subtotal' => round($subtotal, 2),
        ]);
        break;

    // ── Delete estimate ─────────────────────────────────────────────
    case 'delete_estimate':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0 && deleteEstimate($id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to delete']);
        }
        break;

    // ── Delete client ───────────────────────────────────────────────
    case 'delete_client':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $count = getClientEstimateCount($id);
            if ($count > 0) {
                echo json_encode(['error' => "Client has $count estimate(s). Delete them first."]);
            } elseif (deleteClient($id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to delete']);
            }
        } else {
            echo json_encode(['error' => 'Invalid ID']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
