<?php

namespace App\Http\Controllers;

use App\Models\TourOperator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Jobs\UpdateTimeSoltSeat;

class TruncateTablesController extends Controller
{
    public function run($table, $tourOperatorId)
    {
        $tourOperator = TourOperator::find($tourOperatorId);

        if ($tourOperator == null) {
            return 'Received unknown tour operator ID <b>' . $tourOperatorId . '</b>';
        }

        // Handle the table
        switch ($table) {
            case 'admins':
                echo $this->truncateAdminsTable($table);
                break;
            case 'customers':
                echo $this->truncateCustomersTable($table, $tourOperatorId);
                break;
            case 'invoice_bookings':
                echo $this->truncateInvoiceBookingsTable($table, $tourOperatorId);
                break;
            case 'invoices':
                echo $this->truncateInvoiceTable($table, $tourOperatorId);
                break;
            case 'tour_booking_history':
                echo $this->truncateTourBookingHistoryTable($table, $tourOperatorId);
                break;
            case 'tour_booking_tips':
                echo $this->truncateTourBookingTipsTable($table, $tourOperatorId);
                break;
            case 'tour_bookings_master':
                echo $this->truncateTourBookingMasterTable($table, $tourOperatorId);
                break;
            case 'tour_bookings_transact':
                echo $this->truncateTourBookingTransactTable($table, $tourOperatorId);
                break;
            case 'tour_operator_email_templates':
                echo $this->truncateTourPackageEmailTemplatesTable($table, $tourOperatorId);
                break;
            case 'tour_operator_staff':
                echo $this->truncateTourOperatorStaffTable($table, $tourOperatorId);
                break;
            case 'tour_operator_staff_roles':
                echo $this->truncateTourOperatorStaffRolesTable($table, $tourOperatorId);
                break;
            case 'tour_operators':
                echo $this->truncateTourOperatorsTable($table, $tourOperatorId);
                break;
            case 'tour_package_photos':
                echo $this->truncateTourPackagePhotosTable($table, $tourOperatorId);
                break;
            case 'tour_package_rates':
                echo $this->truncateTourPackageRatesTable($table, $tourOperatorId);
                break;
            case 'tour_packages':
                echo $this->truncateTourPackagesTable($table, $tourOperatorId);
                break;
            case 'tour_promotions':
                echo $this->truncateTourPromotionsTable($table, $tourOperatorId);
                break;
            case 'tour_slot_dates':
                echo $this->truncateTourSlotDatesTable($table, $tourOperatorId);
                break;
            case 'tour_slot_times':
                echo $this->truncateTourSlotTimesTable($table, $tourOperatorId);
                break;
            case 'transactions':
                echo $this->truncateTransactionsTable($table, $tourOperatorId);
                break;
            case 'records_history':
                echo $this->truncateRecordHistoryTable($table, $tourOperatorId);
                break;
            case 'affiliate_slot_times':
                echo $this->truncateAffiliateSlotTimesTable($table, $tourOperatorId);
                break;
            case 'tour_slot_times_affiliate':
                echo $this->truncateTourSlotTimesAffiliateTable($table, $tourOperatorId);
                break;
            case 'tour_package_images':
                echo $this->truncateTourPackageImagesTable($table, $tourOperatorId);
                break;
            default:
                echo 'Received unknown table ' . $table;
        }

        http_response_code(200);
    }

    /**
     * Accept table to modified it
     * 
     * @param string $table
     * @return mixed
     */
    public function runMigrations()
    {
        // $tourOperator = TourOperator::find($tourOperatorId);

        // if ($tourOperator == null) {
        //     return 'Received unknown tour operator ID <b>' . $tourOperatorId . '</b>';
        // }

        // $tables = [
        //     // 'admins',
        //     'customers',
        //     'invoice_bookings',
        //     'invoices',
        //     'tour_booking_history',
        //     'tour_booking_tips',
        //     'tour_bookings_master',
        //     'tour_bookings_transact',
        //     'tour_operator_email_templates',
        //     'tour_operator_staff',
        //     'tour_operator_staff_roles',
        //     'tour_operators',
        //     'tour_package_photos',
        //     'tour_package_rates',
        //     'tour_packages',
        //     'tour_promotions',
        //     'tour_slot_dates',
        //     'tour_slot_times',
        //     'transactions',
        //     'records_history',
        //     'affiliate_slot_times',
        //     'tour_slot_times_affiliate',
        //     'tour_package_images',
        // ];

        // foreach ($tables as $table) {
        //     // Handle the table
        //     switch ($table) {
        //         case 'admins':
        //             echo $this->truncateAdminsTable($table);
        //             break;
        //         case 'customers':
        //             echo $this->truncateCustomersTable($table, $tourOperatorId);
        //             break;
        //         case 'invoice_bookings':
        //             echo $this->truncateInvoiceBookingsTable($table, $tourOperatorId);
        //             break;
        //         case 'invoices':
        //             echo $this->truncateInvoiceTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_booking_history':
        //             echo $this->truncateTourBookingHistoryTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_booking_tips':
        //             echo $this->truncateTourBookingTipsTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_bookings_master':
        //             echo $this->truncateTourBookingMasterTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_bookings_transact':
        //             echo $this->truncateTourBookingTransactTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_operator_email_templates':
        //             echo $this->truncateTourPackageEmailTemplatesTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_operator_staff':
        //             echo $this->truncateTourOperatorStaffTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_operator_staff_roles':
        //             echo $this->truncateTourOperatorStaffRolesTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_operators':
        //             echo $this->truncateTourOperatorsTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_package_photos':
        //             echo $this->truncateTourPackagePhotosTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_package_rates':
        //             echo $this->truncateTourPackageRatesTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_packages':
        //             echo $this->truncateTourPackagesTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_promotions':
        //             echo $this->truncateTourPromotionsTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_slot_dates':
        //             echo $this->truncateTourSlotDatesTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_slot_times':
        //             echo $this->truncateTourSlotTimesTable($table, $tourOperatorId);
        //             break;
        //         case 'transactions':
        //             echo $this->truncateTransactionsTable($table, $tourOperatorId);
        //             break;
        //         case 'records_history':
        //             echo $this->truncateRecordHistoryTable($table, $tourOperatorId);
        //             break;
        //         case 'affiliate_slot_times':
        //             echo $this->truncateAffiliateSlotTimesTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_slot_times_affiliate':
        //             echo $this->truncateTourSlotTimesAffiliateTable($table, $tourOperatorId);
        //             break;
        //         case 'tour_package_images':
        //             echo $this->truncateTourPackageImagesTable($table, $tourOperatorId);
        //             break;
        //         default:
        //             echo 'Received unknown table <b>' . $table . '</b>';
        //     }
        // }

        // To update the migrations table when clone existing database with data
        echo $this->truncateAndUpdateMigrationsTable();

        http_response_code(200);
    }

    /**
     * Modify the admins table.
     * 
     * @param string $table
     * @return mixed
     */
    public function truncateAdminsTable($table)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE deleted_by = 0 LIMIT 1");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the customers table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateCustomersTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE tour_operator_id = $tourOperatorId AND deleted_by = 0");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the invoice_bookings table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateInvoiceBookingsTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT invb.* FROM $table invb
            JOIN tour_bookings_master tbm on tbm.id = invb.tour_booking_id
            JOIN tour_packages tp on tp.id = tbm.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the invoices table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateInvoiceTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE tour_operator_id = $tourOperatorId AND deleted_by IS NULL");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_booking_history table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourBookingHistoryTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tbh.* FROM $table tbh
            JOIN tour_bookings_master tbm on tbm.id = tbh.tour_booking_id
            JOIN tour_packages tp on tp.id = tbm.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_booking_tips table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourBookingTipsTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tbt.* FROM $table tbt
            JOIN tour_bookings_master tbm on tbm.id = tbt.tour_booking_id
            JOIN tour_packages tp on tp.id = tbm.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tbt.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_bookings_master table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourBookingMasterTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tbm.* FROM $table tbm
            JOIN tour_packages tp on tp.id = tbm.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tbm.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_bookings_transact table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourBookingTransactTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tbt.* FROM $table tbt
            JOIN tour_bookings_master tbm on tbm.id = tbt.tour_booking_id
            JOIN tour_packages tp on tp.id = tbm.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tbt.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_operator_email_templates table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourPackageEmailTemplatesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE tour_operator_id = $tourOperatorId");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_operator_staff table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourOperatorStaffTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE tour_operator_id = $tourOperatorId AND deleted_by = 0");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_operator_staff_roles table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourOperatorStaffRolesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tbsr.* FROM $table tbsr
            JOIN tour_operator_staff tos on tos.id = tbsr.tour_operator_staff_id
            WHERE tos.tour_operator_id = $tourOperatorId
            AND tbsr.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_operators table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourOperatorsTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE id = $tourOperatorId AND deleted_by = 0");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_package_photos table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourPackagePhotosTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tpp.* FROM $table tpp
            JOIN tour_packages tp on tp.id = tpp.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tpp.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_package_rates table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourPackageRatesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tpr.* FROM $table tpr
            JOIN tour_packages tp on tp.id = tpr.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tpr.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_packages table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourPackagesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE tour_operator_id = $tourOperatorId AND deleted_by = 0");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_promotions table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourPromotionsTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("INSERT INTO temp_$table SELECT * FROM $table WHERE tour_operator_id = $tourOperatorId AND deleted_by = 0");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_slot_dates table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourSlotDatesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tsd.* FROM $table tsd
            JOIN tour_packages tp on tp.id = tsd.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tsd.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_slot_times table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourSlotTimesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tst.* FROM $table tst
            JOIN tour_slot_dates tsd on tsd.id = tst.tour_slot_id
            JOIN tour_packages tp on tp.id = tsd.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND tst.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the transactions table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTransactionsTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT t.* FROM $table t
            JOIN tour_bookings_master tbm on tbm.id = t.tour_booking_id
            JOIN tour_packages tp on tp.id = tbm.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
            AND t.deleted_by = 0
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the records_history table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateRecordHistoryTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT rh.* FROM $table rh
            JOIN tour_packages tp on tp.id = rh.record_id_2
            WHERE tp.tour_operator_id = $tourOperatorId
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the affiliate_slot_times table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateAffiliateSlotTimesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT ast.* FROM $table ast
            JOIN tour_slot_times_affiliate tsta on tsta.id = ast.tour_slot_time_id
            JOIN tour_packages tp on tp.id = tsta.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_slot_times_affiliate table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourSlotTimesAffiliateTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tsta.* FROM $table tsta
            JOIN tour_packages tp on tp.id = tsta.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the tour_package_images table.
     * 
     * @param string $table
     * @param string $tourOperatorId
     * @return mixed
     */
    public function truncateTourPackageImagesTable($table, $tourOperatorId)
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("CREATE TEMPORARY TABLE temp_$table LIKE $table");
        DB::statement("
            INSERT INTO temp_$table 
            SELECT tpi.* FROM $table tpi
            JOIN tour_packages tp on tp.id = tpi.tour_package_id
            WHERE tp.tour_operator_id = $tourOperatorId
        ");
        DB::statement("TRUNCATE TABLE $table");
        DB::statement("INSERT INTO $table SELECT * FROM temp_$table");

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>$table</b> - table modified<br><br>";
    }

    /**
     * Modify the migrations table.
     * 
     * @return mixed
     */
    public function truncateAndUpdateMigrationsTable()
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");

        DB::statement("TRUNCATE TABLE migrations");

        $path = base_path() . '/database/migrations/tenant';
        $files = File::allFiles($path);

        $migration = [];
        foreach ($files as $batch => $file) {
            $migration[] = [
                'migration' => $file->getFilenameWithoutExtension(),
                'batch' => $batch + 1
            ];
        }

        DB::table('migrations')->insert($migration);

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        return "<b>migrations</b> - table modified<br><br>";
    }

    /**
     * Method to update the seats in the time slot table
     * 
     * @param int $tourPackageId
     */
    public function timeSlotSeatsUpdate($tourPackageId)
    {
        DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->join('tour_operators AS t_op', 't_op.id', '=', 'tp.tour_operator_id')
            ->where('tst.deleted_by', 0)
            ->where('tsd.deleted_by', 0)
            ->where('tsd.tour_package_id', $tourPackageId)
            ->select([
                'tsd.id',
                'tsd.seats',
                'tsd.date',
                'tst.time_from',
                'tst.bookable_status',
                'tsd.tour_package_id'
            ])
            ->orderBy('tsd.id')
            ->chunk(500, function ($data) {
                dispatch(new UpdateTimeSoltSeat($data));
            });

        return "<b> Seats modification will be formed in the background shortly; please check back in 5 minutes.</b>";
    }
}
