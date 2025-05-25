<?php
// process_detailed_proposal.php - Generates SVG floor plan with smooth scrolling and web-inspired design

// Initialize error and warning arrays
$error_messages = [];
$warning_messages = [];

// Room area estimates (sq ft)
function get_estimated_area($type, &$warning_messages) {
    $sizes = [
        'foyer' => 60, 'porch' => 80, 'bedroom' => 120, 'bath' => 40, 'kitchen' => 80,
        'diningRoom' => 120, 'lounge' => 200, 'drawingRoom' => 150, 'guestRoom' => 120,
        'store' => 50, 'powderWashroom' => 35, 'library' => 80, 'prayerRoom' => 60,
        'servantQuarter' => 75, 'lawn' => 200, 'garage' => 200, 'openTerrace' => 100,
        'wardrobeSeparate' => 20, 'room' => 100, 'parkingspace' => 150, 'washroom' => 40,
        'staircase' => 50, 'attachedBath' => 12.25 // Smaller washroom (3.5x3.5 ft)
    ];
    if (!isset($sizes[$type])) {
        $warning_messages[] = "Unknown room type '$type'. Using default 50 sq ft.";
        return 50;
    }
    return $sizes[$type];
}

// Parse and validate form data
$proposalData = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING) ?? [];
$homeType = htmlspecialchars($proposalData['homeType'] ?? 'Modern');
$location = htmlspecialchars($proposalData['location'] ?? 'N/A');
$totalAreaInput = filter_input(INPUT_POST, 'area', FILTER_VALIDATE_FLOAT) ?? 0;
$totalAreaUnit = htmlspecialchars($proposalData['areaUnit'] ?? 'sqft');
$numFloors = max(1, filter_input(INPUT_POST, 'floors', FILTER_VALIDATE_INT) ?? 1);
$hasBasement = isset($proposalData['basement']) && $proposalData['basement'] === 'yes';

// Convert area to sqft
$totalAreaSqFt = $totalAreaInput * match ($totalAreaUnit) {
    'marla' => 272.25,
    'kanal' => 5445,
    'squareYards' => 9,
    'squareMeters' => 10.764,
    default => 1,
};

// Validate total area
if ($totalAreaSqFt <= 0) {
    $error_messages[] = "Total area must be greater than 0 sq ft.";
} elseif ($totalAreaSqFt < 300) {
    $warning_messages[] = "Total area ($totalAreaSqFt sq ft) is below recommended minimum (300 sq ft).";
}

// Calculate area per level
$totalLevels = $numFloors + ($hasBasement ? 1 : 0);
$areaPerLevel = $totalLevels > 0 ? $totalAreaSqFt / $totalLevels : 0;
if ($areaPerLevel < 150) {
    $warning_messages[] = "Area per level (~$areaPerLevel sq ft) is below recommended minimum (150 sq ft).";
}

// Build levelsData from form
$levelsData = [];
if ($hasBasement) {
    $basementRooms = [];
    $basementRoomCount = max(0, filter_input(INPUT_POST, 'basementRooms', FILTER_VALIDATE_INT) ?? 0);
    for ($i = 1; $i <= $basementRoomCount; $i++) {
        $basementRooms[] = ['type' => 'room', 'name' => "ROOM $i"];
    }
    if (isset($proposalData['basementStore'])) {
        $basementRooms[] = ['type' => 'store', 'name' => 'STORAGE'];
    }
    if (isset($proposalData['basementParking'])) {
        $basementRooms[] = ['type' => 'parkingspace', 'name' => 'PARKING'];
    }
    if (isset($proposalData['basementWashroom'])) {
        $basementRooms[] = ['type' => 'washroom', 'name' => 'WASHROOM'];
    }
    $levelsData['basement'] = ['type' => 'BASEMENT', 'rooms' => $basementRooms];
}

for ($i = 0; $i < $numFloors; $i++) {
    $floorType = $i === 0 ? 'GROUND FLOOR' : "FLOOR " . ($i + 1);
    $floorRooms = [];
    if ($i === 0) {
        $floorRooms[] = ['type' => 'foyer', 'name' => 'FOYER'];
    }
    $bedroomCount = max(0, filter_input(INPUT_POST, "floor_{$i}_bedrooms", FILTER_VALIDATE_INT) ?? 0);
    for ($j = 1; $j <= $bedroomCount; $j++) {
        $roomDetails = ['type' => 'bedroom', 'name' => "BEDROOM $j"];
        if (isset($proposalData["floor_{$i}_attachedBath_{$j}"])) {
            $roomDetails['features'][] = ['type' => 'attachedBath', 'name' => "BATH $j"];
        }
        if (isset($proposalData["floor_{$i}_wardrobe_{$j}"])) {
            $wardrobeLocations = [];
            if (isset($proposalData["floor_{$i}_wardrobeBedroom_{$j}"])) {
                $wardrobeLocations[] = 'IN BED';
            }
            if (isset($proposalData["floor_{$i}_wardrobeWashroom_{$j}"])) {
                $wardrobeLocations[] = 'IN WASHROOM';
            }
            if (isset($proposalData["floor_{$i}_wardrobeSeparate_{$j}"])) {
                $wardrobeLocations[] = 'SEPARATE';
                $roomDetails['features'][] = ['type' => 'wardrobeSeparate', 'name' => 'W.I.C.'];
            }
            if ($wardrobeLocations) {
                $roomDetails['features'][] = ['type' => 'wardrobe', 'name' => 'WRD (' . implode('/', $wardrobeLocations) . ')'];
            }
        }
        $floorRooms[] = $roomDetails;
    }
    $features = [
        'drawingRoom' => 'DRAWING ROOM', 'tvLounge' => 'TV LOUNGE', 'diningRoom' => 'DINING ROOM',
        'kitchen' => 'KITCHEN', 'guestRoom' => 'GUEST ROOM', 'store' => 'STORAGE',
        'powderWashroom' => 'POWDER WASHROOM', 'library' => 'LIBRARY', 'prayerRoom' => 'PRAYER ROOM',
        'servantQuarter' => 'SERVANT QUARTER', 'lawn' => 'LAWN', 'garage' => 'GARAGE',
        'openTerrace' => 'OPEN TERRACE'
    ];
    foreach ($features as $key => $label) {
        if (isset($proposalData["floor_{$i}_{$key}"])) {
            if (($key === 'lawn' || $key === 'garage') && $i !== 0) continue;
            if ($key === 'openTerrace' && $i === 0) continue;
            $floorRooms[] = ['type' => $key, 'name' => $label];
        }
    }
    if ($numFloors > 1 || $hasBasement) {
        $floorRooms[] = ['type' => 'staircase', 'name' => 'STAIRCASE'];
    }
    if ($i === 0 && in_array('LAWN', array_column($floorRooms, 'name'))) {
        $floorRooms[] = ['type' => 'porch', 'name' => 'PORCH'];
    }
    $levelsData["floor_{$i}"] = ['type' => $floorType, 'rooms' => $floorRooms];
}

// Placeholder for importing plans from web (e.g., via API)
// /*
// $ch = curl_init('https://api.example.com/floorplans');
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// $externalPlan = json_decode(curl_exec($ch), true);
// if ($externalPlan) {
//     $levelsData = array_merge($levelsData, $externalPlan['levels']);
// }
// curl_close($ch);
// */

// --- SVG GENERATION ---
$svg_content = '';
$margin = 50;
$level_spacing = 120;
$scale = 12; // 1 ft = 12 px
$wall_thick = 8;
$int_wall_thick = 4;
$room_spacing = 0; // No spacing for adjacent rooms

// Modern color palette inspired by web floor plan tools
$room_colors = [
    'bedroom' => '#e3f4ff', 'bath' => '#b2d8ff', 'kitchen' => '#fff4e3',
    'lounge' => '#d4e4ff', 'drawingRoom' => '#e3ffed', 'diningRoom' => '#ffe3f0',
    'foyer' => '#f0f0f0', 'porch' => '#d9d4d0', 'store' => '#d0d8e0',
    'staircase' => '#e8f0c3', 'garage' => '#b8c4ca', 'openTerrace' => '#ffe8cc',
    'lawn' => '#c0e8c9', 'guestRoom' => '#f0e3ff', 'library' => '#ffe8e3',
    'powderWashroom' => '#a3e0ff', 'prayerRoom' => '#fff8d4', 'servantQuarter' => '#f0ffe3',
    'wardrobeSeparate' => '#ffd4e3', 'parkingspace' => '#e0e0e0', 'washroom' => '#a3e8f0',
    'room' => '#f0f0f0', 'attachedBath' => '#a3e8f0'
];

// Pre-calculate uniform floor size
$max_cols = 0;
$max_rooms = 0;
foreach ($levelsData as $level) {
    $room_count = count($level['rooms']);
    $cols = min(3, $room_count);
    if ($cols > $max_cols) $max_cols = $cols;
    if ($room_count > $max_rooms) $max_rooms = $room_count;
}
$grid_cols = $max_cols;
$grid_rows = ceil($max_rooms / $grid_cols);
$level_width = 0;
$level_height = 0;

if (!empty($levelsData) && empty($error_messages)) {
    // Calculate room dimensions for all levels to determine max level size
    $all_room_dims = [];
    foreach ($levelsData as $levelKey => $level) {
        $rooms = $level['rooms'];
        $total_room_area = 0;
        foreach ($rooms as $room) {
            $area = get_estimated_area($room['type'], $warning_messages);
            $total_room_area += $area;
            if (isset($room['features'])) {
                foreach ($room['features'] as $feature) {
                    if ($feature['type'] === 'attachedBath' || $feature['type'] === 'wardrobeSeparate') {
                        $total_room_area += get_estimated_area($feature['type'], $warning_messages);
                    }
                }
            }
        }
        $area_scale = $total_room_area > 0 ? min(1, $areaPerLevel / $total_room_area * 0.85) : 1; // Reserve 15% for circulation
        $room_dims = [];
        foreach ($rooms as $room) {
            $area = get_estimated_area($room['type'], $warning_messages) * $area_scale;
            $width = max(10, sqrt($area) * 1.3); // In feet, wider for furniture
            $height = max(8, $area / $width); // In feet
            $room_dims[] = [
                'w' => $width * $scale, // Convert to pixels
                'h' => $height * $scale,
                'room' => $room,
                'area' => $area
            ];
        }
        $all_room_dims[$levelKey] = $room_dims;

        // Calculate level size
        $max_room_width = max(array_column($room_dims, 'w') + [300]);
        $max_room_height = max(array_column($room_dims, 'h') + [100]);
        $calc_width = $grid_cols * $max_room_width + $wall_thick * 2 + $int_wall_thick * ($grid_cols - 1);
        $calc_height = $grid_rows * $max_room_height + $wall_thick * 2 + $int_wall_thick * ($grid_rows - 1);
        if ($calc_width > $level_width) $level_width = $calc_width;
        if ($calc_height > $level_height) $level_height = $calc_height;
    }

    // Calculate SVG dimensions
    $svg_width = $level_width + $margin * 2;
    $svg_height = ($level_height + $level_spacing) * $totalLevels + $margin * 2 + 300; // Extra for legend

    // Initialize SVG
    $svg_content = '<svg id="floorPlanSvg" viewBox="0 0 ' . $svg_width . ' ' . $svg_height . '" xmlns="http://www.w3.org/2000/svg">';
    $svg_content .= '<defs>';
    $svg_content .= '<pattern id="lawn-pattern" patternUnits="userSpaceOnUse" width="20" height="20">';
    $svg_content .= '<path d="M0 10H20M10 0V20" stroke="#4caf50" stroke-width="1"/>';
    $svg_content .= '</pattern>';
    $svg_content .= '<linearGradient id="modern-gradient" x1="0" y1="0" x2="1" y2="1">';
    $svg_content .= '<stop offset="0%" stop-color="#f0f8ff"/>';
    $svg_content .= '<stop offset="100%" stop-color="#d6eaff"/>';
    $svg_content .= '</linearGradient>';
    $svg_content .= '<filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">';
    $svg_content .= '<feGaussianBlur in="SourceAlpha" stdDeviation="3"/>';
    $svg_content .= '<feOffset dx="2" dy="2" result="offsetblur"/>';
    $svg_content .= '<feComponentTransfer><feFuncA type="linear" slope="0.5"/></feComponentTransfer>';
    $svg_content .= '<feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>';
    $svg_content .= '</filter>';
    $svg_content .= '</defs>';
    $svg_content .= '<style>
        .ext-wall { stroke: #1a1a1a; stroke-width: ' . $wall_thick . '; fill: none; filter: url(#shadow); }
        .int-wall { stroke: #4a4a4a; stroke-width: ' . $int_wall_thick . '; fill: none; }
        .door { stroke: #6b3e2e; stroke-width: 3; fill: #f8f1e9; }
        .window { stroke: #2a5b84; stroke-width: 3; fill: #a3d5ff; }
        .label { font-family: \'Roboto\', Arial, sans-serif; font-size: 14px; fill: #1a1a1a; font-weight: 500; }
        .floor-label { font-family: \'Roboto\', Arial, sans-serif; font-size: 18px; fill: #333; font-weight: 700; }
        .legend-label { font-family: \'Roboto\', Arial, sans-serif; font-size: 13px; fill: #1a1a1a; }
        .furniture { fill: #666; stroke: #444; stroke-width: 1; }
        .interactive-room { cursor: pointer; transition: transform 0.2s; }
        .interactive-room:hover, .interactive-room:focus { transform: scale(1.02); }
        .lawn-fill { fill: url(#lawn-pattern); }
        .lawn-fill:not(:first-child) { fill: #c0e8c9; }
        .modern-fill { fill: url(#modern-gradient); }
        .modern-fill:not(:first-child) { fill: #f0f8ff; }
        * { fill: none; stroke: none; }
        .tooltip {
            position: fixed;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: \'Roboto\', Arial, sans-serif;
            font-size: 12px;
            pointer-events: none;
            z-index: 1000;
            display: none;
            transition: opacity 0.2s;
        }
    </style>';

    // Draw each floor
    $current_y = $margin;
    $max_width = 0;
    foreach ($levelsData as $levelKey => $level) {
        $rooms = $level['rooms'];
        $levelType = $level['type'];
        $room_dims = $all_room_dims[$levelKey];

        // Draw exterior wall for the level
        $svg_content .= '<rect x="' . $margin . '" y="' . $current_y . '" width="' . $level_width . '" height="' . $level_height . '" class="ext-wall"/>';
        $svg_content .= '<text x="' . ($margin + $level_width / 2) . '" y="' . ($current_y - 10) . '" class="floor-label" text-anchor="middle">' . htmlspecialchars($levelType) . '</text>';

        // Place rooms in grid, ensuring shared walls
        $y = $current_y + $wall_thick;
        foreach (array_chunk($room_dims, $grid_cols) as $row_idx => $row) {
            if (empty($row)) continue;
            $x = $margin + $wall_thick;
            foreach ($row as $col_idx => $rd) {
                $w = $rd['w'];
                $h = $rd['h'];
                $room = $rd['room'];
                $area = $rd['area'];
                $fill = $room_colors[$room['type']] ?? '#f0f0f0';

                // Draw room rectangle with interactivity
                $tooltip = htmlspecialchars($room['name'] . ' (' . $room['type'] . ', ' . number_format($area, 1) . ' sq ft)');
                $svg_content .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" class="int-wall interactive-room" fill="' . $fill . '" data-tooltip="' . $tooltip . '"/>';

                // Room label
                $label_y = $y + $h / 2;
                if ($room['type'] === 'bedroom' && isset($room['features'])) {
                    $has_attached_bath = false;
                    foreach ($room['features'] as $feature) {
                        if ($feature['type'] === 'attachedBath') {
                            $has_attached_bath = true;
                            break;
                        }
                    }
                    if ($has_attached_bath) {
                        $label_y = $y + $h * 0.25; // Adjust label to avoid smaller bath
                    }
                }
                $svg_content .= '<text x="' . ($x + $w / 2) . '" y="' . $label_y . '" class="label" text-anchor="middle" alignment-baseline="middle" data-tooltip="' . $tooltip . '">' . htmlspecialchars($room['name']) . '</text>';

                // Attached washroom and wardrobe in bedroom
                if ($room['type'] === 'bedroom' && isset($room['features']) && is_array($room['features'])) {
                    foreach ($room['features'] as $feature) {
                        if ($feature['type'] === 'attachedBath') {
                            // Draw attached bathroom (3.5x3.5 ft) in top-right corner
                            $bath_w = min($w * 0.25, 3.5 * $scale);
                            $bath_h = min($h * 0.25, 3.5 * $scale);
                            $bath_x = $x + $w - $bath_w - 5;
                            $bath_y = $y + 5;
                            $bath_fill = $room_colors['attachedBath'];
                            $bath_area = get_estimated_area('attachedBath', $warning_messages);
                            $bath_tooltip = htmlspecialchars($feature['name'] . ' (Attached Bath, ' . number_format($bath_area, 1) . ' sq ft)');
                            $svg_content .= '<rect x="' . $bath_x . '" y="' . $bath_y . '" width="' . $bath_w . '" height="' . $bath_h . '" class="int-wall interactive-room" fill="' . $bath_fill . '" data-tooltip="' . $bath_tooltip . '"/>';
                            $svg_content .= '<text x="' . ($bath_x + $bath_w / 2) . '" y="' . ($bath_y + $bath_h / 2) . '" class="label" font-size="9" text-anchor="middle" alignment-baseline="middle" data-tooltip="' . $bath_tooltip . '">' . htmlspecialchars($feature['name']) . '</text>';
                            // Bathroom furniture (smaller scale)
                            $svg_content .= '<ellipse class="furniture" cx="' . ($bath_x + $bath_w * 0.3) . '" cy="' . ($bath_y + $bath_h * 0.7) . '" rx="' . (0.5 * $scale) . '" ry="' . (0.5 * $scale) . '"/>'; // Toilet
                            $svg_content .= '<ellipse class="furniture" cx="' . ($bath_x + $bath_w * 0.7) . '" cy="' . ($bath_y + $bath_h * 0.3) . '" rx="' . (0.5 * $scale) . '" ry="' . (0.25 * $scale) . '"/>'; // Sink
                            // Internal door to bathroom
                            $svg_content .= '<rect class="door" x="' . ($bath_x - 2) . '" y="' . ($bath_y + $bath_h / 2 - 10) . '" width="4" height="20"/>';
                        }
                        if ($feature['type'] === 'wardrobe' && strpos($feature['name'], 'IN BED') !== false) {
                            // Wardrobe in bedroom (4x2 ft, top wall)
                            $wardrobe_w = min($w * 0.2, 4 * $scale);
                            $wardrobe_h = min($h * 0.1, 2 * $scale);
                            $svg_content .= '<rect class="furniture" x="' . ($x + $w - $wardrobe_w - 10) . '" y="' . ($y + 10) . '" width="' . $wardrobe_w . '" height="' . $wardrobe_h . '" rx="4"/>';
                        }
                    }
                }

                // Separate wardrobe room
                if ($room['type'] === 'wardrobeSeparate') {
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.2) . '" y="' . ($y + $h * 0.2) . '" width="' . min($w * 0.6, 4 * $scale) . '" height="' . min($h * 0.6, 4 * $scale) . '" rx="4"/>';
                }

                // Other furniture by room type
                if ($room['type'] === 'bedroom') {
                    // Bed (6x6 ft)
                    $bed_w = min($w * 0.5, 6 * $scale);
                    $bed_h = min($h * 0.3, 6 * $scale);
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1) . '" y="' . ($y + $h - $bed_h - 10) . '" width="' . $bed_w . '" height="' . $bed_h . '" rx="8"/>';
                    // Nightstands (2x2 ft each)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1 - 2 * $scale) . '" y="' . ($y + $h - $bed_h - 10) . '" width="' . (2 * $scale) . '" height="' . (2 * $scale) . '" rx="3"/>';
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1 + $bed_w) . '" y="' . ($y + $h - $bed_h - 10) . '" width="' . (2 * $scale) . '" height="' . (2 * $scale) . '" rx="3"/>';
                }
                if ($room['type'] === 'lounge' || $room['type'] === 'drawingRoom') {
                    // Sofa (6x3 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1) . '" y="' . ($y + $h * 0.7) . '" width="' . min($w * 0.5, 6 * $scale) . '" height="' . min($h * 0.18, 3 * $scale) . '" rx="8"/>';
                    // Coffee table (4x2 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.4) . '" y="' . ($y + $h * 0.5) . '" width="' . (4 * $scale) . '" height="' . (2 * $scale) . '" rx="4"/>';
                    // TV (3x1 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.7) . '" y="' . ($y + 10) . '" width="' . (3 * $scale) . '" height="' . (1 * $scale) . '" rx="2"/>';
                }
                if ($room['type'] === 'diningRoom') {
                    // Dining table (6x3 ft)
                    $table_w = min($w * 0.5, 6 * $scale);
                    $table_h = min($h * 0.3, 3 * $scale);
                    $svg_content .= '<ellipse class="furniture" cx="' . ($x + $w / 2) . '" cy="' . ($y + $h / 2) . '" rx="' . ($table_w / 2) . '" ry="' . ($table_h / 2) . '"/>';
                    // Chairs (2x2 ft each, 6 chairs)
                    for ($c = 0; $c < 6; $c++) {
                        $angle = deg2rad($c * 60);
                        $cx = ($x + $w / 2) + cos($angle) * ($table_w / 2 + 2 * $scale);
                        $cy = ($y + $h / 2) + sin($angle) * ($table_h / 2 + 2 * $scale);
                        $svg_content .= '<rect class="furniture" x="' . ($cx - 1 * $scale) . '" y="' . ($cy - 1 * $scale) . '" width="' . (2 * $scale) . '" height="' . (2 * $scale) . '" rx="3"/>';
                    }
                }
                if ($room['type'] === 'kitchen') {
                    // Counter (6x2 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1) . '" y="' . ($y + 10) . '" width="' . min($w * 0.7, 6 * $scale) . '" height="' . (2 * $scale) . '" rx="4"/>';
                    // Sink (2x1 ft)
                    $svg_content .= '<ellipse class="furniture" cx="' . ($x + $w * 0.8) . '" cy="' . ($y + 1.5 * $scale) . '" rx="' . (1 * $scale) . '" ry="' . (0.5 * $scale) . '"/>';
                    // Stove (3x2 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1) . '" y="' . ($y + $h - 2 * $scale - 10) . '" width="' . (3 * $scale) . '" height="' . (2 * $scale) . '" rx="4"/>';
                }
                if ($room['type'] === 'library') {
                    // Bookshelves (6x3 ft, 2 units)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1) . '" y="' . ($y + 10) . '" width="' . min($w * 0.7, 6 * $scale) . '" height="' . (3 * $scale) . '" rx="4"/>';
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.1) . '" y="' . ($y + $h - 3 * $scale - 10) . '" width="' . min($w * 0.7, 6 * $scale) . '" height="' . (3 * $scale) . '" rx="4"/>';
                    // Reading table (4x2 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.4) . '" y="' . ($y + $h * 0.5) . '" width="' . (4 * $scale) . '" height="' . (2 * $scale) . '" rx="4"/>';
                }
                if ($room['type'] === 'foyer') {
                    // Console table (4x1 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.3) . '" y="' . ($y + $h - 1 * $scale - 10) . '" width="' . (4 * $scale) . '" height="' . (1 * $scale) . '" rx="4"/>';
                }
                if ($room['type'] === 'garage') {
                    // Car (10x5 ft)
                    $svg_content .= '<rect class="furniture" x="' . ($x + $w * 0.2) . '" y="' . ($y + $h * 0.3) . '" width="' . min($w * 0.6, 10 * $scale) . '" height="' . min($h * 0.4, 5 * $scale) . '" rx="6"/>';
                }
                if (in_array($room['type'], ['washroom', 'powderWashroom'])) {
                    // Toilet (2x2 ft)
                    $svg_content .= '<ellipse class="furniture" cx="' . ($x + $w * 0.8) . '" cy="' . ($y + $h * 0.8) . '" rx="' . (1 * $scale) . '" ry="' . (1 * $scale) . '"/>';
                    // Sink (2x1 ft)
                    $svg_content .= '<ellipse class="furniture" cx="' . ($x + $w * 0.8) . '" cy="' . ($y + 1.5 * $scale) . '" rx="' . (1 * $scale) . '" ry="' . (0.5 * $scale) . '"/>';
                }

                // Doors at shared walls
                if ($col_idx < count($row) - 1) {
                    // Door to next room (right wall)
                    $svg_content .= '<rect class="door" x="' . ($x + $w - 2) . '" y="' . ($y + $h / 2 - 10) . '" width="4" height="20"/>';
                }
                if ($row_idx < $grid_rows - 1) {
                    // Door to room below
                    $svg_content .= '<rect class="door" x="' . ($x + $w / 2 - 10) . '" y="' . ($y + $h - 2) . '" width="20" height="4"/>';
                }
                if ($col_idx == 0 && $row_idx == 0 && $room['type'] === 'foyer') {
                    // Main entrance door (bottom wall)
                    $svg_content .= '<rect class="door" x="' . ($x + $w / 2 - 10) . '" y="' . ($y + $h - 5) . '" width="20" height="8"/>';
                }

                // Windows on exterior walls
                if ($row_idx == 0) {
                    // Window on top wall
                    $svg_content .= '<rect class="window" x="' . ($x + $w / 2 - 15) . '" y="' . ($y - 7) . '" width="30" height="7"/>';
                }
                if ($col_idx == count($row) - 1) {
                    // Window on right wall
                    $svg_content .= '<rect class="window" x="' . ($x + $w - 7) . '" y="' . ($y + $h / 2 - 15) . '" width="7" height="30"/>';
                }
                if ($row_idx == $grid_rows - 1) {
                    // Window on bottom wall
                    $svg_content .= '<rect class="window" x="' . ($x + $w / 2 - 15) . '" y="' . ($y + $h - 7) . '" width="30" height="7"/>';
                }

                $x += $w + $int_wall_thick;
            }
            $y += max(array_column($row, 'h') + [100]) + $int_wall_thick;
        }
        $current_y += $level_height + $level_spacing;
        if ($level_width > $max_width) $max_width = $level_width;
    }

    // North arrow
    $svg_content .= '<g><polygon points="' . ($max_width + 30) . ',60 ' . ($max_width + 20) . ',80 ' . ($max_width + 40) . ',80" fill="#1a1a1a"/>';
    $svg_content .= '<text x="' . ($max_width + 30) . '" y="55" font-size="14" fill="#1a1a1a" text-anchor="middle">N</text></g>';

    // Legend
    $legend_x = $margin;
    $legend_y = $current_y + 30;
    $legend_items = [];
    foreach ($room_colors as $type => $color) {
        if (in_array($type, array_column(array_merge(...array_column($levelsData, 'rooms')), 'type'))) {
            $legend_items[] = ['color' => $color, 'label' => ucfirst(str_replace('Room', ' Room', $type))];
        }
    }
    $legend_height = count($legend_items) * 20 + 100;
    $svg_content .= '<g>';
    $svg_content .= '<rect x="' . $legend_x . '" y="' . $legend_y . '" width="420" height="' . $legend_height . '" fill="#fff" stroke="#4a4a4a" stroke-width="1" filter="url(#shadow)"/>';
    $svg_content .= '<text x="' . ($legend_x + 10) . '" y="' . ($legend_y + 25) . '" class="legend-label" font-weight="bold">Legend</text>';
    $ly = $legend_y + 45;
    foreach ($legend_items as $li) {
        $svg_content .= '<rect x="' . ($legend_x + 10) . '" y="' . $ly . '" width="22" height="16" fill="' . $li['color'] . '" stroke="#4a4a4a" stroke-width="1"/>';
        $svg_content .= '<text x="' . ($legend_x + 40) . '" y="' . ($ly + 13) . '" class="legend-label">' . htmlspecialchars($li['label']) . '</text>';
        $ly += 20;
    }
    // Legend: Doors, windows, furniture
    $svg_content .= '<rect class="door" x="' . ($legend_x + 180) . '" y="' . ($legend_y + 45) . '" width="22" height="8"/>';
    $svg_content .= '<text x="' . ($legend_x + 210) . '" y="' . ($legend_y + 55) . '" class="legend-label">Door</text>';
    $svg_content .= '<rect class="window" x="' . ($legend_x + 180) . '" y="' . ($legend_y + 70) . '" width="22" height="8"/>';
    $svg_content .= '<text x="' . ($legend_x + 210) . '" y="' . ($legend_y + 80) . '" class="legend-label">Window</text>';
    $svg_content .= '<rect class="furniture" x="' . ($legend_x + 180) . '" y="' . ($legend_y + 95) . '" width="22" height="10" rx="3"/>';
    $svg_content .= '<text x="' . ($legend_x + 210) . '" y="' . ($legend_y + 105) . '" class="legend-label">Bed/Sofa/Table</text>';
    $svg_content .= '<ellipse class="furniture" cx="' . ($legend_x + 191) . '" cy="' . ($legend_y + 125) . '" rx="11" ry="7"/>';
    $svg_content .= '<text x="' . ($legend_x + 210) . '" y="' . ($legend_y + 130) . '" class="legend-label">Sink/Toilet</text>';
    $svg_content .= '</g>';

    $svg_content .= '</svg>';
} else {
    $warning_messages[] = "No valid levels to generate a floor plan.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated House Floor Plan</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/snap.svg@0.5.1/dist/snap.svg-min.js"></script>
    <style>
        body {
            background-color: #f7fafc;
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .floor-plan-wrapper {
            width: 100%;
            height: 80vh;
            overflow: auto;
            margin: 1.5rem 0;
            position: relative;
            scroll-behavior: smooth;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        #floorPlanSvg {
            display: block;
            max-width: 100%;
            height: auto;
            background-color: #f9fafb;
        }
        .controls {
            position: sticky;
            top: 0.5rem;
            left: 0.5rem;
            z-index: 10;
            background: rgba(255, 255, 255, 0.95);
            padding: 0.5rem;
            border-radius: 0.375rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .grid {
            display: none;
        }
        .grid-line {
            stroke: #d1d5db;
            stroke-width: 0.5;
            stroke-dasharray: 2,2;
        }
    </style>
</head>
<body>
    <div class="container mt-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Generated House Floor Plan</h1>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
            <p class="font-semibold">Disclaimer:</p>
            <p>This SVG is a visual approximation. Consult an architect for precise plans.</p>
        </div>

        <?php if (!empty($error_messages)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p class="font-semibold">Errors:</p>
                <ul class="list-disc pl-5">
                    <?php foreach ($error_messages as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($warning_messages)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-semibold">Warnings:</p>
                <ul class="list-disc pl-5">
                    <?php foreach ($warning_messages as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($svg_content && empty($error_messages)): ?>
            <p class="text-gray-600 mb-4">Below is a schematic representation of your floor plan based on the inputs provided.</p>
            <div class="floor-plan-wrapper">
                <div class="controls flex space-x-2">
                    <button id="toggleGrid" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition">Toggle Grid</button>
                    <button id="resetView" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition">Reset View</button>
                </div>
                <?php echo $svg_content; ?>
            </div>
        <?php else: ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
                <p>No floor plan generated. Please check your input.</p>
            </div>
        <?php endif; ?>

        <div class="mt-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-3">Proposal Summary</h2>
            <p class="text-gray-700"><strong>Home Type:</strong> <?php echo htmlspecialchars($homeType); ?></p>
            <p class="text-gray-700"><strong>Location:</strong> <?php echo htmlspecialchars($location); ?></p>
            <p class="text-gray-700"><strong>Total Area:</strong> <?php echo htmlspecialchars("$totalAreaInput $totalAreaUnit (" . number_format($totalAreaSqFt, 2) . " sq ft)"); ?></p>
            <p class="text-gray-700"><strong>Floors:</strong> <?php echo htmlspecialchars($numFloors); ?></p>
            <p class="text-gray-700"><strong>Basement:</strong> <?php echo $hasBasement ? 'Yes' : 'No'; ?></p>
            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Level Details:</h3>
            <ul class="list-disc pl-5 text-gray-700">
                <?php foreach ($levelsData as $level): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($level['type']); ?>:</strong>
                        <?php echo htmlspecialchars(implode(', ', array_column($level['rooms'], 'name'))); ?>
                        <?php foreach ($level['rooms'] as $room): if ($room['type'] === 'bedroom' && !empty($room['features'])): ?>
                            <br><?php echo htmlspecialchars($room['name']); ?> features: <?php echo htmlspecialchars(implode(', ', array_column($room['features'], 'name'))); ?>
                        <?php endif; endforeach; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <a href="test.php" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition mt-8 mb-8">Back to Form</a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const svg = document.getElementById("floorPlanSvg");
            const wrapper = document.querySelector(".floor-plan-wrapper");
            const toggleGridBtn = document.getElementById("toggleGrid");
            const resetViewBtn = document.getElementById("resetView");
            const grid = svg?.querySelector(".grid");
            const initialViewBox = "0 0 <?php echo $svg_width; ?> <?php echo $svg_height; ?>";

            // Debug: Check for missing elements
            if (!svg || !wrapper) {
                console.error("SVG (#floorPlanSvg) or wrapper (.floor-plan-wrapper) not found.");
                return;
            }
            if (!toggleGridBtn || !resetViewBtn) {
                console.error("Control buttons (toggleGrid, resetView) not found.");
                return;
            }
            if (!grid) {
                console.warn("Grid element (.grid) not found; generating grid.");
                const g = document.createElementNS("http://www.w3.org/2000/svg", "g");
                g.classList.add("grid");
                for (let x = 0; x < <?php echo $svg_width; ?>; x += 50) {
                    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                    line.setAttribute("x1", x);
                    line.setAttribute("y1", 0);
                    line.setAttribute("x2", x);
                    line.setAttribute("y2", <?php echo $svg_height; ?>);
                    line.classList.add("grid-line");
                    g.appendChild(line);
                }
                for (let y = 0; y < <?php echo $svg_height; ?>; y += 50) {
                    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                    line.setAttribute("x1", 0);
                    line.setAttribute("y1", y);
                    line.setAttribute("x2", <?php echo $svg_width; ?>);
                    line.setAttribute("y2", y);
                    line.classList.add("grid-line");
                    g.appendChild(line);
                }
                svg.insertBefore(g, svg.firstChild);
            }

            // Grid toggle
            toggleGridBtn.addEventListener("click", function() {
                const grid = svg.querySelector(".grid");
                grid.style.display = grid.style.display === "none" ? "block" : "none";
                console.log("Grid toggled:", grid.style.display);
            });

            // Reset view
            resetViewBtn.addEventListener("click", function() {
                svg.setAttribute("viewBox", initialViewBox);
                svg.style.transform = "none";
                wrapper.scrollTo({ top: 0, left: 0, behavior: "smooth" });
                console.log("View reset to:", initialViewBox);
            });

            // Smooth scrolling with wheel
            let scrollVelocity = { x: 0, y: 0 };
            wrapper.addEventListener("wheel", function(e) {
                e.preventDefault();
                const deltaX = e.deltaX * 0.5;
                const deltaY = e.deltaY * 0.5;
                scrollVelocity.x += deltaX;
                scrollVelocity.y += deltaY;

                const animateScroll = () => {
                    wrapper.scrollLeft += scrollVelocity.x;
                    wrapper.scrollTop += scrollVelocity.y;
                    scrollVelocity.x *= 0.9;
                    scrollVelocity.y *= 0.9;
                    if (Math.abs(scrollVelocity.x) > 0.1 || Math.abs(scrollVelocity.y) > 0.1) {
                        requestAnimationFrame(animateScroll);
                    }
                };
                requestAnimationFrame(animateScroll);
                console.log("Smooth scroll:", wrapper.scrollLeft, wrapper.scrollTop);
            }, { passive: false });

            // Tooltip handling with debouncing
            const tooltip = document.createElement("div");
            tooltip.className = "tooltip";
            document.body.appendChild(tooltip);
            let tooltipTimeout;

            svg.querySelectorAll(".interactive-room, .label").forEach(function(el) {
                el.addEventListener("mouseenter", function(e) {
                    clearTimeout(tooltipTimeout);
                    const tooltipText = el.getAttribute("data-tooltip");
                    if (tooltipText) {
                        tooltip.textContent = tooltipText;
                        tooltip.style.display = "block";
                        tooltip.style.opacity = "1";
                        const rect = el.getBoundingClientRect();
                        tooltip.style.left = (rect.left + window.scrollX + rect.width / 2) + "px";
                        tooltip.style.top = (rect.top + window.scrollY - 30) + "px";
                        console.log("Tooltip shown for:", tooltipText);
                    }
                });
                el.addEventListener("mousemove", function(e) {
                    tooltip.style.left = (e.clientX + 10) + "px";
                    tooltip.style.top = (e.clientY + 10) + "px";
                });
                el.addEventListener("mouseleave", function() {
                    tooltipTimeout = setTimeout(() => {
                        tooltip.style.opacity = "0";
                        setTimeout(() => { tooltip.style.display = "none"; }, 200);
                        console.log("Tooltip hidden");
                    }, 100);
                });
            });

            // Zoom and pan
            let viewBox = svg.viewBox.baseVal;
            let scale = 1;
            let startX, startY, isPanning = false;

            svg.addEventListener("mousedown", function(e) {
                if (e.button === 0) {
                    isPanning = true;
                    startX = e.clientX;
                    startY = e.clientY;
                    console.log("Panning started at:", startX, startY);
                }
            });

            svg.addEventListener("mousemove", function(e) {
                if (isPanning) {
                    const dx = (e.clientX - startX) / scale;
                    const dy = (e.clientY - startY) / scale;
                    viewBox.x -= dx;
                    viewBox.y -= dy;
                    svg.setAttribute("viewBox", `${viewBox.x} ${viewBox.y} ${viewBox.width} ${viewBox.height}`);
                    startX = e.clientX;
                    startY = e.clientY;
                    console.log("Panning to viewBox:", viewBox.x, viewBox.y);
                }
            });

            svg.addEventListener("mouseup", function() {
                isPanning = false;
                console.log("Panning stopped");
            });

            svg.addEventListener("wheel", function(e) {
                e.preventDefault();
                const delta = e.deltaY > 0 ? 0.9 : 1.1;
                scale *= delta;
                viewBox.width = <?php echo $svg_width; ?> / scale;
                viewBox.height = <?php echo $svg_height; ?> / scale;
                viewBox.x += (e.clientX / scale - viewBox.x) * (1 - delta);
                viewBox.y += (e.clientY / scale - viewBox.y) * (1 - delta);
                svg.setAttribute("viewBox", `${viewBox.x} ${viewBox.y} ${viewBox.width} ${viewBox.height}`);
                console.log("Zoomed, scale:", scale, "viewBox:", viewBox.x, viewBox.y, viewBox.width, viewBox.height);
            }, { passive: false });

            // Snap.svg animations for interactive rooms
            if (typeof Snap !== "undefined") {
                const s = Snap("#floorPlanSvg");
                s.selectAll(".interactive-room").forEach(function(el) {
                    el.mouseover(function() {
                        el.animate({ transform: "s1.02,1.02" }, 200);
                    });
                    el.mouseout(function() {
                        el.animate({ transform: "s1,1" }, 200);
                    });
                });
                console.log("Snap.svg initialized for animations");
            } else {
                console.warn("Snap.svg not loaded; animations disabled.");
            }
        });
    </script>
</body>
</html>