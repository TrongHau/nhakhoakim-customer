<?php

namespace App\InsuranceHub;

interface InterfaceProviderConfig
{

    /**
     * Các bước quy trình BLVP theo thứ tự, bao gồm cả các trạng thái kết thúc.
     *
     * @return array<array{stepNumber:int, status:string, label:string, description:string, isFinal:bool}>
     */
    public static function requestWorkflowSteps(): array;

    public static function claimWorkflowSteps(): array;

    /**
     * Map UnifiedStatus → danh sách action key khả dụng ở trạng thái đó.
     *
     * @return array<string, string[]>  [EnumStatus::DRAFT => ['submit', 'cancel'], ...]
     */
    public static function availableActions(): array;

    /**
     * Metadata chi tiết cho mỗi action key.
     *
     * @return array<string, array{label:string, method:string, endpoint:string, requiresConfirmation:bool}>
     */
    public static function actionMeta(): array;

    /**
     * Base URL API của provider theo APP_ENV.
     * testing | staging → UAT endpoint
     * production        → PROD endpoint
     */
    public static function baseUrl(): string;
}
