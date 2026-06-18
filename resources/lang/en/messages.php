<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Language Lines
    |--------------------------------------------------------------------------
    */

    'create_success' => 'Create :name successfully.',
    'update_success' => 'Update :name successfully.',
    'delete_success' => 'Delete :name successfully.',
    'delete_success_of_parent' => 'Delete :name of :parent successfully.',
    'close_success' => 'Close :name successfully.',

    'create_fail' => 'Failed to create :name. Please check and try again.',
    'update_fail' => 'Failed to create :name. Please check and try again.',
    'delete_fail' => 'Failed to create :name. Please check and try again.',
    'close_fail' => 'Failed to close :name. Please check and try again.',

    'item_not_exist' => 'The :name does not exist in the system.',
    'item_exist' => 'The :name already exists in the system.',
    'a_exist_in_b' => 'The :namea already exists in the :nameb.',

    'action_success' => 'Action completed successfully.',
    'action_fail' => 'Action Failed.',
    'action_x_success' => 'Action :name completed successfully.',
    'action_x_fail' => 'Action :name Failed.',

    'create_existed' => 'The :name already exists in the system. Please check and try again.',

    'user_not_available_to_assign' => "User :userIds not available to assign.",
    'search_phone_min_length' => "Search by phone number must be at least 4 characters.",
    /*
     * Campaign
     */
    'campaign_have_no_leads' => 'Campaign is empty. Please import before you assign leads to agents.',
    'reach_limit_today' => ':name reached limit today',
    'campaign_assign_not_enough_leads_use_suggest_data' => 'Numbers of available leads are less than your assign numbers. Please use suggest data or re-enter the input.',
    'campaign_assign_data_empty' => 'Campaign assign data is empty or invalid.',
    'campaign_assign_success_x_lead_to_team_y' => 'Assigned :x lead success to group :y.',

    /*
     * Lead
     */
    'lead_assigned' => 'Lead already has been assigned.',
    'lead_already_assigned_to_this_user' => 'Lead already has assigned to this user.',
    'lead_import_success_x_row' => 'Import success :count leads into the system.',
    'lead_import_invalid_x_row' => 'The file has :count invalid Leads.',
    'lead_import_duplicate_x_row' => 'The file has :count leads which exists in the system',
    'lead_import_invalid_format' => 'The file is invalid or wrong format.',
    'lead_have_no_activity_available_to_assign' => 'Activities of this Lead are processing or finished. Cannot to assign.',
    /*
     * Call
     */
    'start_call_success' => 'Start Call Success',
    'start_call_fail' => 'Start Call Fail',
    /*
     *  Appointment
     */
    'contact_already_have_appointment_in_day' => "Contact :name already have an appointment in day.",
    'create_appointment_fail' => 'Failed to create appointment for :name. Please check and try again.',

    /*
     *  Activity
     */
    'you_are_on_another_call' => 'You are processing another :name. Please finish it before you process a new :name.',
    'hot_data_assigned_to_another_user' => 'Hot Data was processing by another user.',
    'hot_data_assigned_success' => 'Hot Data was assigned to you success.',
    'relationship_main' => 'Main',

    /*
 * My Activity
 */
    'create_follow_customer_success' => 'Create following customer success.',
    'create_follow_customer_fail' => 'Create following customer fail.',
    'no_follow_process_completed_success' => 'Un-follow customer success..',
    'no_follow_process_completed_fail' => 'Un-follow customer fail. Please try again.',
    'create_follow_opportunity_success' => 'Create activity to follow opportunity success.',
    'create_follow_opportunity_fail' => 'Create activity to follow opportuinity fail. Please try again',

    /*
     * Contact
     */
    'customer_has_no_contact' => 'Customer has not qualify yet or has no contact',
    'customer_has_no_phone' => 'Customer :name has not phone number. Please check and retry again.',
    'phone_number_exists_pls_create_relationship' => 'Phone Number already exists in the system. Please create relationship if exists contact is your relationship or update exists contact to new phone number and try again.',
    /*
    |--------------------------------------------------------------------------
    | Custom Attributes
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'campaign' => 'Campaign',
        'contact' => 'Contact',
        'opportunity' => 'Opportunity',
        'activity' => 'Activity',
        'next_activity' => 'Next Activity',
        'lead' => 'Lead',
        'user' => 'User',
        'team' => 'Team',
        'extension' => 'Extension',
        'contact_relationship' => 'Relationship',
        'appointment' => 'Appointment',
        'call'  => 'Call',
        'follow_opp' => 'Remind',
        'remind' => 'Remind',
    ],
    'actions' => [
        'import' => 'Import',
        'assign' => 'Assign',
    ]
];

