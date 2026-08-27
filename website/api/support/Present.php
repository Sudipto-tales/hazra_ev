<?php

/**
 * Database row -> wire object, one method per model in lib/data/models/.
 *
 * Field names here are the Dart field names, not the column names. Nothing
 * outside this file decides what a payload looks like.
 */
final class Present
{
    // ---------------------------------------------------------------- identity

    /**
     * `reportingTo` is a rendered string in the app today; the id is carried
     * alongside it so the client can stop rendering it eventually (data doc §13).
     */
    public static function employee(array $r): array
    {
        return [
            'type'           => 'employee',
            'id'             => $r['id'],
            'employeeCode'   => $r['employee_code'] ?? '',
            'name'           => $r['name'],
            'designation'    => $r['designation'] ?? '',
            'department'     => $r['department'] ?? '',
            'email'          => $r['email'],
            'phone'          => $r['phone'] ?? '',
            'avatarUrl'      => $r['avatar_url'] ?? '',
            'bannerUrl'      => $r['banner_url'] ?? '',
            'joinedOn'       => Wire::date($r['joined_on'] ?? null),
            'reportingTo'    => $r['reporting_to_label'] ?? '',
            'reportingToId'  => $r['reports_to_id'] ?? null,
            'region'         => $r['emp_region'] ?? $r['region'] ?? '',
            'bloodGroup'     => $r['blood_group'] ?? '',
            'address'        => $r['address'] ?? '',
            'active'         => (bool) ($r['active'] ?? 1),
            'initials'       => Wire::initials($r['name']),
        ];
    }

    public static function admin(array $r): array
    {
        return [
            'type'      => 'admin',
            'id'        => $r['id'],
            'adminCode' => $r['admin_code'] ?? '',
            'name'      => $r['name'],
            'role'      => $r['role_title'] ?? '',
            'email'     => $r['email'],
            'phone'     => $r['phone'] ?? '',
            'avatarUrl' => $r['avatar_url'] ?? '',
            'region'    => $r['admin_region'] ?? $r['region'] ?? '',
            'initials'  => Wire::initials($r['name']),
        ];
    }

    /**
     * `principal.type` decides which shell boots — the client does not pick.
     *
     * `mustChangePassword` is added here and not in employee()/admin() so a
     * roster row stays exactly what it was: this is a fact about the signed-in
     * session, not a field of the record.
     */
    public static function principal(array $r): array
    {
        $principal = $r['role'] === 'admin' ? self::admin($r) : self::employee($r);
        $principal['mustChangePassword'] = (bool) ($r['must_change_password'] ?? 0);

        return $principal;
    }

    public static function preferences(array $r): array
    {
        return [
            'themeMode'            => $r['theme_mode'] ?? 'system',
            'language'             => $r['language'] ?? 'English',
            'notificationsEnabled' => (bool) ($r['notifications_enabled'] ?? 1),
            'reportReminders'      => (bool) ($r['report_reminders'] ?? 1),
            'sessionReminders'     => (bool) ($r['session_reminders'] ?? 1),
            'systemNotifications'  => (bool) ($r['system_notifications'] ?? 1),
            'highAccuracyMode'     => (bool) ($r['high_accuracy_mode'] ?? 1),
            'syncOnMobileData'     => (bool) ($r['sync_on_mobile_data'] ?? 1),
            'batterySaver'         => (bool) ($r['battery_saver'] ?? 0),
        ];
    }

    // ------------------------------------------------------------- aggregates

    /** The nine DaySummary numbers. Durations are integer seconds. */
    public static function daySummary(?array $r): array
    {
        return [
            'joiningTime'       => Wire::ts($r['joining_time'] ?? null),
            'endTime'           => Wire::ts($r['end_time'] ?? null),
            'workedDuration'    => Wire::int($r['worked_seconds'] ?? 0),
            'sessionCount'      => Wire::int($r['session_count'] ?? 0),
            'distanceKm'        => Wire::float($r['distance_km'] ?? 0),
            'companiesVisited'  => Wire::int($r['companies_visited'] ?? 0),
            'reportsSubmitted'  => Wire::int($r['reports_submitted'] ?? 0),
            'stopDuration'      => Wire::int($r['stop_seconds'] ?? 0),
            'longestStop'       => Wire::int($r['longest_stop_seconds'] ?? 0),
        ];
    }

    /**
     * Attendance embeds the summary rather than repeating the nine fields at
     * the top level — the §12 de-duplication made concrete (API plan §3.5).
     */
    public static function attendance(array $r): array
    {
        return [
            'id'      => ($r['employee_id'] ?? '') . ':' . Wire::date($r['work_date']),
            'date'    => Wire::date($r['work_date']),
            'status'  => Wire::enum($r['status'] ?? 'no_data'),
            'summary' => self::daySummary($r),
        ];
    }

    /**
     * The end-of-day declaration. Carries the measured figures next to the
     * declared ones so the admin card can show the gap without a second call —
     * and so the gap survives even if the day is later recomputed.
     */
    public static function closeout(array $r): array
    {
        $tags = Wire::json($r['tags'] ?? null, []);

        return [
            'id'                   => $r['id'],
            'date'                 => Wire::date($r['work_date']),
            'submittedAt'          => Wire::ts($r['submitted_at']),
            'declaredDistanceKm'   => Wire::float($r['declared_distance_km'] ?? 0),
            'declaredVisits'       => Wire::int($r['declared_visits'] ?? 0),
            'measuredDistanceKm'   => Wire::float($r['measured_distance_km'] ?? 0),
            'measuredVisits'       => Wire::int($r['measured_visits'] ?? 0),
            'rating'               => Wire::int($r['rating'] ?? 0),
            // Stored snake_case, sent camelCase: vehicle_issue -> vehicleIssue.
            'tags'                 => array_values(array_map(
                [Wire::class, 'enum'],
                is_array($tags) ? $tags : [],
            )),
            'feedback'             => Wire::text($r['feedback'] ?? null),
        ];
    }

    // ------------------------------------------------------- sessions & fixes

    public static function session(array $r): array
    {
        return [
            'id'              => $r['id'],
            'index'           => Wire::int($r['seq']),
            'startTime'       => Wire::ts($r['started_at']),
            'endTime'         => Wire::ts($r['ended_at'] ?? null),
            'distanceKm'      => Wire::float($r['distance_km'] ?? 0),
            'locationPoints'  => Wire::int($r['point_count'] ?? 0),
            'startLatitude'   => Wire::float($r['start_lat'] ?? 0),
            'startLongitude'  => Wire::float($r['start_lng'] ?? 0),
        ];
    }

    /** The server only ever returns `synced` — queued/failed are device state. */
    public static function fix(?array $r): ?array
    {
        if (!$r) {
            return null;
        }

        return [
            'id'         => $r['client_id'],
            'sessionId'  => $r['session_id'],
            'latitude'   => Wire::float($r['lat']),
            'longitude'  => Wire::float($r['lng']),
            'accuracy'   => Wire::float($r['accuracy_m']),
            'speedKmh'   => Wire::float($r['speed_kmh']),
            'recordedAt' => Wire::ts($r['recorded_at']),
            'syncState'  => 'synced',
        ];
    }

    public static function stop(array $r): array
    {
        return [
            'id'            => $r['id'],
            'sessionId'     => $r['session_id'],
            'arrival'       => Wire::ts($r['arrival']),
            'departure'     => Wire::ts($r['departure'] ?? null),
            'latitude'      => Wire::float($r['lat']),
            'longitude'     => Wire::float($r['lng']),
            'radiusMetres'  => Wire::float($r['radius_m']),
            'visitId'       => $r['visit_id'] ?? null,
        ];
    }

    /** A visit can carry many reports — reportIds is never collapsed to one. */
    public static function visit(array $r, array $reportIds = []): array
    {
        return [
            'id'             => $r['id'],
            'sessionId'      => $r['session_id'],
            'companyId'      => $r['company_id'],
            'branchId'       => $r['branch_id'] ?? null,
            'stopId'         => $r['stop_id'] ?? null,
            'arrival'        => Wire::ts($r['arrival']),
            'departure'      => Wire::ts($r['departure'] ?? null),
            'latitude'       => Wire::float($r['lat']),
            'longitude'      => Wire::float($r['lng']),
            'status'         => Wire::enum($r['status']),
            'reportIds'      => array_values($reportIds),
            'dealReference'  => $r['deal_reference'] ?? null,
        ];
    }

    // ----------------------------------------------------------------- reports

    public static function saleLine(array $r): array
    {
        return [
            'productId'   => $r['product_id'],
            'productName' => $r['product_name'],
            'category'    => Wire::enum($r['category']),
            'colorName'   => $r['color_name'] ?? '',
            'colorArgb'   => Wire::int($r['color_argb']),
            'units'       => Wire::int($r['units']),
        ];
    }

    /** `dealValue` and `paymentReceived` stay the text the seller typed. */
    public static function report(array $r, array $sales = [], int $imageCount = 0): array
    {
        return [
            'id'              => $r['id'],
            'companyName'     => $r['company_name'],
            'branchName'      => $r['branch_name'] ?? null,
            'companyId'       => $r['company_id'] ?? null,
            'branchId'        => $r['branch_id'] ?? null,
            'visitId'         => $r['visit_id'] ?? null,
            'sessionId'       => $r['session_id'] ?? '',
            'title'           => $r['title'],
            'body'            => $r['body'] ?? '',
            'imageCount'      => $imageCount,
            'submittedAt'     => Wire::ts($r['submitted_at']),
            'latitude'        => Wire::float($r['lat']),
            'longitude'       => Wire::float($r['lng']),
            'status'          => Wire::enum($r['status']),
            'dealValue'       => Wire::text($r['deal_value'] ?? null),
            'followUpOn'      => Wire::date($r['follow_up_on'] ?? null),
            'sales'           => array_map([self::class, 'saleLine'], $sales),
            'paymentReceived' => Wire::text($r['payment_received'] ?? null),
        ];
    }

    public static function review(string $reportId, ?array $r): array
    {
        return [
            'reportId'   => $reportId,
            'decision'   => Wire::enum($r['decision'] ?? 'pending'),
            'note'       => $r['note'] ?? null,
            'reviewedBy' => $r['reviewed_by'] ?? null,
            'reviewedAt' => Wire::ts($r['reviewed_at'] ?? null),
        ];
    }

    public static function image(array $r): array
    {
        return [
            'id'           => $r['id'],
            'reportId'     => $r['report_id'],
            'url'          => $r['url'],
            'thumbnailUrl' => $r['thumbnail_url'] ?? null,
            'width'        => $r['width'] === null ? null : Wire::int($r['width']),
            'height'       => $r['height'] === null ? null : Wire::int($r['height']),
            'bytes'        => $r['bytes'] === null ? null : Wire::int($r['bytes']),
            'capturedAt'   => Wire::ts($r['captured_at'] ?? null),
            'latitude'     => $r['lat'] === null ? null : Wire::float($r['lat']),
            'longitude'    => $r['lng'] === null ? null : Wire::float($r['lng']),
            'uploadState'  => 'uploaded',
        ];
    }

    // --------------------------------------------------------------- customers

    public static function company(array $r, array $branches = []): array
    {
        return [
            'id'       => $r['id'],
            'name'     => $r['name'],
            'category' => $r['category'] ?? '',
            'branches' => array_map([self::class, 'branch'], $branches),
        ];
    }

    public static function branch(array $r): array
    {
        return [
            'id'        => $r['id'],
            'name'      => $r['name'],
            'address'   => $r['address'] ?? '',
            'latitude'  => Wire::float($r['lat']),
            'longitude' => Wire::float($r['lng']),
        ];
    }

    // --------------------------------------------------------------- catalogue

    /** No price field, on Product or ProductDraft. There must not be one. */
    public static function product(array $r, array $colors = []): array
    {
        return [
            'id'               => $r['id'],
            'category'         => Wire::enum($r['category']),
            'brand'            => $r['brand'],
            'name'             => $r['name'],
            'modelCode'        => $r['model_code'],
            'rating'           => Wire::float($r['rating']),
            'warrantyYears'    => Wire::int($r['warranty_years']),
            'warrantyNote'     => $r['warranty_note'] ?? '',
            'rangeKm'          => Wire::int($r['range_km']),
            'topSpeedKmph'     => Wire::int($r['top_speed_kmph']),
            'chargingTime'     => $r['charging_time'] ?? '',
            'batteryCapacity'  => $r['battery_capacity'] ?? '',
            'motorPower'       => $r['motor_power'] ?? '',
            'loadCapacityKg'   => Wire::int($r['load_capacity_kg']),
            'colors'           => $colors,
            'highlights'       => Wire::json($r['highlights'] ?? null, []),
            'listedAt'         => Wire::ts($r['listed_at'] ?? null),
            'active'           => (bool) ($r['active'] ?? 1),
        ];
    }

    /** The gallery is per colour, so imageUrls belongs on the colour. */
    public static function productColor(array $r, array $imageUrls = []): array
    {
        return [
            'name'      => $r['name'],
            'argb'      => Wire::int($r['argb']),
            'imageUrls' => array_values($imageUrls),
            'inStock'   => (bool) ($r['in_stock'] ?? 1),
        ];
    }

    // ------------------------------------------------------- timeline & feed

    public static function activity(array $r): array
    {
        return [
            'id'          => $r['id'],
            'type'        => Wire::enum($r['type']),
            'time'        => Wire::ts($r['occurred_at']),
            'endTime'     => Wire::ts($r['end_at'] ?? null),
            'title'       => $r['title'],
            'subtitle'    => $r['subtitle'] ?? null,
            'companyName' => $r['company_name'] ?? null,
            'branchName'  => $r['branch_name'] ?? null,
            'duration'    => $r['duration_seconds'] === null ? null : Wire::int($r['duration_seconds']),
            'reportId'    => $r['report_id'] ?? null,
            'visitId'     => $r['visit_id'] ?? null,
            'isAlert'     => (bool) ($r['is_alert'] ?? 0),
        ];
    }

    public static function notification(array $r): array
    {
        return [
            'id'        => $r['id'],
            'kind'      => Wire::enum($r['kind']),
            'title'     => $r['title'],
            'message'   => $r['message'] ?? '',
            'createdAt' => Wire::ts($r['created_at']),
            'read'      => !empty($r['read_at']),
            'productId' => $r['product_id'] ?? null,
            'reportId'  => $r['report_id'] ?? null,
        ];
    }

    // ------------------------------------------------------------------ config

    public static function config(array $r): array
    {
        return [
            'locationIntervalSeconds'              => Wire::int($r['location_interval_seconds']),
            'minAccuracyMetres'                    => Wire::float($r['min_accuracy_metres']),
            'stopRadiusMetres'                     => Wire::float($r['stop_radius_metres']),
            'stopThresholdMinutes'                 => Wire::int($r['stop_threshold_minutes']),
            'longStopThresholdMinutes'             => Wire::int($r['long_stop_threshold_minutes']),
            'movementSpeedThresholdKmh'            => Wire::float($r['movement_speed_threshold_kmh']),
            'maxJumpKmh'                           => Wire::float($r['max_jump_kmh']),
            'offlineThresholdMinutes'              => Wire::int($r['offline_threshold_minutes']),
            'locationUnavailableThresholdMinutes'  => Wire::int($r['location_unavailable_threshold_minutes']),
            'syncBatchSize'                        => Wire::int($r['sync_batch_size']),
            'version'                              => Wire::int($r['version']),
            'updatedAt'                            => Wire::ts($r['updated_at']),
            'updatedBy'                            => $r['updated_by'] ?? null,
        ];
    }

    // ------------------------------------------------------------ admin views

    public static function teamMember(array $m): array
    {
        return [
            'employee'           => $m['employee'],
            'status'             => $m['status'],
            'movement'           => $m['movement'],
            'locationHealth'     => $m['locationHealth'],
            'summary'            => $m['summary'],
            'attendanceStatus'   => $m['attendanceStatus'],
            'active'             => $m['active'],
            'lastFix'            => $m['lastFix'],
            'activeSince'        => $m['activeSince'],
            'openStopDuration'   => $m['openStopDuration'],
            'openStopCompanyId'  => $m['openStopCompanyId'],
        ];
    }

    public static function dailyMetric(string $date, float $distanceKm, int $visits): array
    {
        return [
            'date'           => $date,
            'value'          => Wire::float($distanceKm),
            'secondaryValue' => $visits,
        ];
    }

    public static function employeeMetric(array $r): array
    {
        return [
            'employeeId'            => $r['employeeId'],
            'name'                  => $r['name'],
            'initials'              => Wire::initials($r['name']),
            'distanceKm'            => Wire::float($r['distanceKm']),
            'visits'                => Wire::int($r['visits']),
            'reports'               => Wire::int($r['reports']),
            'presentDays'           => Wire::int($r['presentDays']),
            'absentDays'            => Wire::int($r['absentDays']),
            'attendancePercent'     => Wire::float($r['attendancePercent']),
            'averageWorkedDuration' => Wire::int($r['averageWorkedDuration']),
        ];
    }
}
