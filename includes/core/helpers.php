<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('plugin_suite_array_get')) {
  function plugin_suite_array_get($array, $keys, $default = null) {
    if (!is_array($array)) return $default;
    foreach ((array)$keys as $key) {
      if (array_key_exists($key, $array) && $array[$key] !== null && $array[$key] !== '') {
        return $array[$key];
      }
    }
    return $default;
  }
}

if (!function_exists('plugin_suite_normalise_bool')) {
  function plugin_suite_normalise_bool($value) {
    if (is_bool($value)) return $value;
    if (is_numeric($value)) return (int)$value === 1;
    $value = strtolower(trim((string)$value));
    return in_array($value, ['1','yes','true','on'], true);
  }
}


if (!function_exists('plugin_suite_age_to_months')) {
  function plugin_suite_age_to_months($age_text) {
    $age = strtolower(trim((string)$age_text));
    if ($age === '') return null;

    $total = 0.0;
    $matched = false;
    if (preg_match('/(\d+(?:\.\d+)?)\s*year/', $age, $m)) { $total += ((float)$m[1]) * 12; $matched = true; }
    if (preg_match('/(\d+(?:\.\d+)?)\s*month/', $age, $m)) { $total += (float)$m[1]; $matched = true; }
    if (preg_match('/(\d+(?:\.\d+)?)\s*week/', $age, $m)) { $total += ((float)$m[1]) / 4.345; $matched = true; }

    if ($matched) return max(0, (int) round($total));
    if (strpos($age, 'kitten') !== false) return 6;
    if (strpos($age, 'senior') !== false) return 120;
    return null;
  }
}

if (!function_exists('plugin_suite_age_band_from_months')) {
  function plugin_suite_age_band_from_months($months) {
    if ($months === null || $months === '') return '';
    $months = (int)$months;
    if ($months < 12) return 'Under 1 year';
    if ($months < 36) return '1 to 3 years';
    if ($months < 60) return '3 to 5 years';
    return '5+ years';
  }
}

if (!function_exists('plugin_suite_normalise_animal')) {
  function plugin_suite_normalise_animal($row) {
    if (!is_array($row)) return [];

    $description = trim((string) plugin_suite_array_get($row, ['ANIMALCOMMENTS','WEBSITEMEDIANOTES','DESCRIPTION','ANIMALDESCRIPTION'], ''));
    $reservation_status = trim((string) plugin_suite_array_get($row, ['primary_reservation_status'], ''));
    $image_count = (int) plugin_suite_array_get($row, ['WEBSITEIMAGECOUNT','WebsiteImageCount','WEBSITEIMAGES','WebsiteImages'], 0);
    $sex = trim((string) plugin_suite_array_get($row, ['SEXNAME','SexName','SEX'], ''));

    return [
      'id' => (string) plugin_suite_array_get($row, ['ID','ANIMALID','AnimalID','animalid'], ''),
      'name' => trim((string) plugin_suite_array_get($row, ['ANIMALNAME','AnimalName','NAME'], '')),
      'code' => trim((string) plugin_suite_array_get($row, ['CODE','ShelterCode','SHELTERCODE'], '')),
      'age_text' => trim((string) plugin_suite_array_get($row, ['ANIMALAGE','AnimalAge'], '')),
      'sex' => $sex,
      'breed' => trim((string) plugin_suite_array_get($row, ['BREEDNAME','BreedName','BREEDNAME1','BreedName1'], '')),
      'age_months' => plugin_suite_array_get($row, ['AGE_MONTHS','age_months','AgeMonths'], plugin_suite_age_to_months(plugin_suite_array_get($row, ['ANIMALAGE','AnimalAge'], ''))),
      'age_band' => trim((string) plugin_suite_array_get($row, ['AGE_BAND','age_band','AgeBand'], '')),
      'species_id' => (int) plugin_suite_array_get($row, ['SPECIESID','SpeciesID','speciesid'], 0),
      'description' => $description,
      'image_count' => max(0, $image_count),
      'days_on_shelter' => (int) plugin_suite_array_get($row, ['DAYSONSHELTER','DaysOnShelter'], 0),
      'has_active_reservation' => plugin_suite_normalise_bool(plugin_suite_array_get($row, ['has_active_reservation','HASACTIVERESERVE','HasActiveReserve','HASACTIVERESERVENAME'], false)),
      'primary_reservation_status' => $reservation_status,
      'is_bonded' => stripos($description, 'bonded with') !== false,
      'is_indoor_only' => stripos($description, 'indoor only') !== false,
    ];
  }
}

if (!function_exists('plugin_suite_animal_matches_filters')) {
  function plugin_suite_animal_matches_filters($animal, $filters = []) {
    $animal = isset($animal['name']) ? $animal : plugin_suite_normalise_animal($animal);
    if (!is_array($filters) || empty($filters)) return true;

    if (!empty($filters['sex']) && strcasecmp((string)$animal['sex'], (string)$filters['sex']) !== 0) return false;
    if (!empty($filters['breed']) && strcasecmp((string)$animal['breed'], (string)$filters['breed']) !== 0) return false;
    if (!empty($filters['hide_pending']) && strcasecmp((string)$animal['primary_reservation_status'], 'Pending Adoption') === 0) return false;
    if (!empty($filters['indoor_only']) && empty($animal['is_indoor_only'])) return false;
    if (!empty($filters['bonded_only']) && empty($animal['is_bonded'])) return false;

    if (!empty($filters['age_group'])) {
      $group = trim((string)$filters['age_group']);
      $animal_band = !empty($animal['age_band']) ? (string)$animal['age_band'] : plugin_suite_age_band_from_months($animal['age_months'] ?? null);
      if ($animal_band === '' || strcasecmp($animal_band, $group) !== 0) return false;
    }

    return true;
  }
}

if (!function_exists('plugin_suite_resolve_image_url')) {
  function plugin_suite_resolve_image_url($animal, $seq = 1) {
    $animal = isset($animal['id']) ? $animal : plugin_suite_normalise_animal($animal);
    $id = preg_replace('/\D+/', '', (string)$animal['id']);
    $seq = max(1, (int)$seq);
    if ($id === '') return '';
    return home_url('/wp-json/plugin/v1/animal-image?animalid=' . rawurlencode($id) . '&seq=' . rawurlencode((string)$seq));
  }
}

if (!function_exists('plugin_suite_collect_labels')) {
  function plugin_suite_collect_labels($animal) {
    $animal = isset($animal['name']) ? $animal : plugin_suite_normalise_animal($animal);
    $labels = [];
    if (!empty($animal['has_active_reservation'])) $labels[] = 'reserved';
    if (!empty($animal['is_bonded'])) $labels[] = 'bonded';
    if (!empty($animal['is_indoor_only'])) $labels[] = 'indoor_only';
    return $labels;
  }
}
