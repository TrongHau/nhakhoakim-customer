<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Language Lines
    |--------------------------------------------------------------------------
    */

    'create_success' => 'Tạo :name thành công.',
    'update_success' => 'Cập nhật :name thành công.',
    'delete_success' => 'Xóa :name thành công.',
    'delete_success_of_parent' => 'Xóa :name của :parent thành công.',
    'close_success' => 'Đóng :name thành công.',

    'create_fail' => 'Có lỗi khi tạo :name. Vui lòng kiểm tra và thử lại.',
    'update_fail' => 'Có lỗi khi cập nhật :name. Vui lòng kiểm tra và thử lại.',
    'delete_fail' => 'Có lỗi khi xóa :name. Vui lòng kiểm tra và thử lại.',
    'close_fail' => 'Có lỗi khi đóng :name. Vui lòng kiểm tra và thử lại.',

    'item_not_exist' => ':name không tồn tại trong hệ thống.',
    'item_exist' => 'Đã tồn tại :name trong hệ thống.',
    'a_exist_in_b' => ':namea đã tồn tại trong :nameb.',

    'action_success' => 'Thao tác thành công.',
    'action_fail' => 'Thao tác thất bại.',
    'action_x_success' => 'Thao tác :name thành công.',
    'action_x_fail' => 'Thao tác :name thất bại.',

    'create_existed' => 'Đã tồn tại :name trong hệ thóng. Vui lòng kiểm tra lại.',

    'user_not_available_to_assign' => "User :userIds hiện tại đang thuộc team khác hoặc không thể phân công.",
    'search_phone_min_length' => "Tìm kiếm số điện thoại phải có tối thiểu 4 ký tự.",
    /*
     * Campaign
     */
    'campaign_have_no_leads' => 'Chiến dịch đang không có lead, vui lòng import dữ liệu trước khi phân công.',
    'reach_limit_today' => ':name đã được phân công tối đa hôm nay',
    'campaign_assign_not_enough_leads_use_suggest_data' => 'Số Leads hiện có không đủ để phân công. Vui lòng nhập lại hoặc sử dụng số đề nghị của hệ thống',
    'campaign_assign_data_empty' => 'Chưa nhập dữ liệu phân công hoặc dữ liệu chưa hợp lệ.',
    'campaign_assign_success_x_lead_to_team_y' => 'Đã phân công thành công :x lead cho thành viên nhóm :y.',

    /*
     * Lead
     */
    'lead_assigned' => 'Lead đã được phân công cho user khác.',
    'lead_already_assigned_to_this_user' => 'Lead hiện tại đã được phân công cho nhân này.',
    'lead_import_success_x_row' => 'Đã nhập thành công :count thông tin vào hệ thống.',
    'lead_import_invalid_x_row' => ':count thông tin không hợp lệ.',
    'lead_import_duplicate_x_row' => ':count thông tin đã tồn tại trong hệ thống.',
    'lead_import_invalid_format' => 'Tệp tin nhập vào không đúng format hoặc rỗng.',
    'lead_have_no_activity_available_to_assign' => 'Các hoạt động của lead này đang được xử lý hoặc đã xử lý hoàn tất. Không thể phân công.',
    /*
     * Call
     */
    'start_call_success' => 'Đang bắt đầu cuộc gọi',
    'start_call_fail' => 'Cuộc gọi không thành công',
    /*
     *  Appointment
     */
    'contact_already_have_appointment_in_day' => "Khách hàng :name đã có lịch hẹn trong ngày.",
    'create_appointment_fail' => 'Có lỗi khi tạo lịch hẹn cho khách hàng :name. Vui lòng kiểm tra và thử lại.',

    /*
     *  Activity
     */
    'you_are_on_another_process' => 'Bạn đang xử lý một :name khác. Vui lòng hoàn tất để xử lý :name mới.',
    'hot_data_assigned_to_another_user' => 'Hot Data đã được nhân viên khác xử lý.',
    'hot_data_assigned_success' => 'Bạn đã lấy Hot Data thành công.',
    'relationship_main' => 'Chính',

    /*
     * My Activity
     */
    'create_follow_customer_success' => 'Tạo hoạt động theo dõi khách hàng thành công.',
    'create_follow_customer_fail' => 'Tạo hoạt động theo dõi khách hàng thất bại.',
    'no_follow_process_completed_success' => 'Không theo dõi khách hàng. Quá trình hoàn tất.',
    'no_follow_process_completed_fail' => 'Quá trình xử lý không theo dõi khách hàng thất bại. Vui lòng thử lại',
    'create_follow_opportunity_success' => 'Tạo hoạt động nhắc đặt lịch hẹn thành công.',
    'create_follow_opportunity_fail' => 'Quá trình tạo nhắc đặt lịch hẹn không thành công. Vui lòng thử lại.',

    /*
     * Contact
     */
    'customer_has_no_contact' => 'Thông tin chưa được xác thực hoặc chưa tạo khách hàng.',
    'customer_has_no_phone' => 'Không thể lấy số điện thoại của khách hàng :name. Vui lòng kiểm tra lại.',
    'phone_number_exists_pls_create_relationship' => 'Số điện thoại đã tồn tại trong hệ thống. Vui lòng tạo người thân cho liên hệ trên hoặc cập nhật số điện thoại của liên hệ đã tồn tại và thử lại.',
    /*
    |--------------------------------------------------------------------------
    | Custom Attributes
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'campaign' => 'Chiến dịch',
        'contact' => 'Liên hệ',
        'opportunity' => 'Cơ hội',
        'activity' => 'Hoạt động',
        'next_activity' => 'Hoạt động kế tiếp',
        'lead' => 'Lead',
        'user' => 'User',
        'team' => 'Nhóm',
        'extension' => 'Số Ext',
        'contact_relationship' => 'Người thân',
        'appointment' => 'Lịch hẹn',
        'call'  => 'Cuộc gọi',
        'follow_opp' => 'Nhắc đặt lịch hẹn',
        'remind' => 'Nhắc hẹn',
    ],
    'actions' => [
        'import' => 'Nhập dữ liệu',
        'assign' => 'Phân công',
    ]
];

