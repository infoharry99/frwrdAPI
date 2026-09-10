<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AuthController as BaseAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends BaseAuthController
{
    /**
     * Helper for paginated & searched table queries
     */
    protected function queryBranchTable(string $table, Request $request)
    {
        $page = (int)$request->query('page', 1);
        $limit = 100;
        $offset = ($page - 1) * $limit;

        $status = $request->query('status', 'all');
        $search = $request->query('search', '');

        $query = DB::table($table);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $data = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
            'data' => $data,
        ]);
    }

    public function getBranch1017Clients(Request $request)
    {
        return $this->queryBranchTable('clients_branch_1017', $request);
    }

    public function getBranch28866Clients(Request $request)
    {
        return $this->queryBranchTable('clients_branch_28866', $request);
    }

    public function getBranch1017Tutors(Request $request)
    {
        return $this->queryBranchTable('tutors_branch_1017', $request);
    }

    public function getBranch28866Tutors(Request $request)
    {
        return $this->queryBranchTable('tutors_branch_28866', $request);
    }

    public function getBranch1017Students(Request $request)
    {
        return $this->queryBranchTable('students_branch_1017', $request);
    }

    public function getBranch28866Students(Request $request)
    {
        return $this->queryBranchTable('students_branch_28866', $request);
    }

    public function getBranch1017Invoices(Request $request)
    {
        $page = (int)$request->query('page', 1);
        $limit = 100;
        $offset = ($page - 1) * $limit;

        $query = DB::table('proforma_invoices_branch_1017');
        $total = $query->count();
        $data = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'total' => $total,
            'data' => $data,
        ]);
    }

    public function getBranch28866Invoices(Request $request)
    {
        $page = (int)$request->query('page', 1);
        $limit = 100;
        $offset = ($page - 1) * $limit;

        $query = DB::table('proforma_invoices_branch_28866');
        $total = $query->count();
        $data = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'total' => $total,
            'data' => $data,
        ]);
    }

    public function getBranch1017StudentsDashboard()
    {
        $total = DB::table('students_branch_1017')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch28866StudentsDashboard()
    {
        $total = DB::table('students_branch_28866')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch1017TutorsDashboard()
    {
        $total = DB::table('tutors_branch_1017')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch28866TutorsDashboard()
    {
        $total = DB::table('tutors_branch_28866')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch1017ClientDashboard($clientId)
    {
        $client = DB::table('clients_branch_1017')->where('id', $clientId)->first();
        return response()->json(['success' => true, 'client' => $client]);
    }

    public function getBranch28866ClientDashboard($clientId)
    {
        $client = DB::table('clients_branch_28866')->where('id', $clientId)->first();
        return response()->json(['success' => true, 'client' => $client]);
    }

    public function getBranch1017InvoicesDashboard()
    {
        $total = DB::table('proforma_invoices_branch_1017')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch28866InvoicesDashboard()
    {
        $total = DB::table('proforma_invoices_branch_28866')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch1017PaymentsDashboard()
    {
        $total = DB::table('payments')->where('branch_id', '1017')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch28866PaymentsDashboard()
    {
        $total = DB::table('payments')->where('branch_id', '28866')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch1017AppointmentsDashboard()
    {
        $total = DB::table('appointments_branch_1017')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch28866AppointmentsDashboard()
    {
        $total = DB::table('appointments_branch_28866')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch1017ClientsDashboard()
    {
        $total = DB::table('clients_branch_1017')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getBranch28866ClientsDashboard()
    {
        $total = DB::table('clients_branch_28866')->count();
        return response()->json(['success' => true, 'total' => $total]);
    }

    public function getClientStudents1017($clientId)
    {
        $rows = DB::table('students_branch_1017')->where('client_id', $clientId)->get();
        return response()->json($rows);
    }

    public function getClientStudents28866($clientId)
    {
        $rows = DB::table('students_branch_28866')->where('client_id', $clientId)->get();
        return response()->json($rows);
    }

    public function getAllClientsDropdown1017()
    {
        $rows = DB::table('clients_branch_1017')->select('id', 'first_name', 'last_name', 'email')->get();
        return response()->json($rows);
    }

    public function getAllClientsDropdown28866()
    {
        $rows = DB::table('clients_branch_28866')->select('id', 'first_name', 'last_name', 'email')->get();
        return response()->json($rows);
    }

    public function getAllTutorsDropdown1017()
    {
        $rows = DB::table('tutors_branch_1017')->select('id', 'first_name', 'last_name', 'email')->get();
        return response()->json($rows);
    }

    public function getAllTutorsDropdown28866()
    {
        $rows = DB::table('tutors_branch_28866')->select('id', 'first_name', 'last_name', 'email')->get();
        return response()->json($rows);
    }

    public function getAllStudentsDropdown1017()
    {
        $rows = DB::table('students_branch_1017')->select('id', 'first_name', 'last_name', 'email')->get();
        return response()->json($rows);
    }

    public function getAllStudentsDropdown28866()
    {
        $rows = DB::table('students_branch_28866')->select('id', 'first_name', 'last_name', 'email')->get();
        return response()->json($rows);
    }

    public function getBranch1017Appointments(Request $request)
    {
        $rows = DB::table('appointments_branch_1017')->limit(100)->get();
        return response()->json($rows);
    }

    public function getBranch28866Appointments(Request $request)
    {
        $rows = DB::table('appointments_branch_28866')->limit(100)->get();
        return response()->json($rows);
    }

    public function filterAppointments1017(Request $request)
    {
        return response()->json([]);
    }

    public function filterAppointments28866(Request $request)
    {
        return response()->json([]);
    }

    public function getAllClients()
    {
        $clients = DB::table('client')->get();
        return response()->json($clients);
    }
}
