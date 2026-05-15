<?php
/**
 * app/Services/SearchTrackingService.php — INSERT dans search_tracking
 *
 * Extrait de index.php (tracking profil + recherche club)
 */

class SearchTrackingService
{
    /**
     * Enregistre une consultation de page dans search_tracking
     *
     * @param mysqli $conn
     * @param string $type    athlete|club|epreuve|ville|general
     * @param string $source  page_view|panel_open|live_search
     * @param int|string $entityId
     * @param string $entityName
     */
    public static function track($conn, $type, $source, $entityId = '', $entityName = '')
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        $ip = trim(explode(',', $ip)[0]);
        if ($ip === '') return;

        $type = $conn->real_escape_string($type);
        $source = $conn->real_escape_string($source);
        $entityId = $conn->real_escape_string($entityId);
        $entityName = $conn->real_escape_string($entityName);
        $ipEsc = $conn->real_escape_string($ip);

        $conn->query("INSERT INTO search_tracking (ip, query_text, search_type, source, entity_id, entity_name, result_count, page, created_at)
            VALUES ('$ipEsc', '', '$type', '$source', '$entityId', '$entityName', 0, '', NOW())");
    }
}
