<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailTemplateRequest;
use App\Models\EmailTemplate;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return mixed
     */
    public function index()
    {
        $vars = [
            'page_title' => config('constants.email_template.page_title')
        ];

        return view('users.admin.email-template.index', $vars);
    }

    /**
     * Json response for DataTable.
     *
     * @return JsonResponse
     */
    public function indexAHR(): JsonResponse
    {
        $query = EmailTemplate::select(
            [
                'id',
                'alias',
                'class',
                'name',
                'created_by',
                'created_from'
            ]
        );

        return Datatables::of($query)
            ->addIndexColumn()
            ->editColumn('action', function ($emailTemplate) {
                $routePrefix = routePrefix();
                return '<a href="' . route($routePrefix . '.master-email-template.edit', ['master_email_template' => $emailTemplate->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .

                    '<button data-url="' . route($routePrefix . '.master-email-template.destroy', ['master_email_template' => $emailTemplate->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i>  Delete</a>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return mixed
     */
    public function create()
    {
        $vars = [
            'page_title' => config('constants.email_template.page_title')
        ];

        return view('users.admin.email-template.create', $vars);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  EmailTemplateRequest  $request
     * @return RedirectResponse
     */
    public function store(EmailTemplateRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();
            EmailTemplate::create($request->all());
            DB::commit();

            return redirect()
                ->route(routePrefix() . '.master-email-template.index')
                ->with('message', 'Email template added.');
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception->getMessage());

            return back()
                ->withErrors(['errors' => 'Unable to add email template.'])
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return mixed
     */
    public function edit($id)
    {
        $emailTemplate = EmailTemplate::findOrFail($id);

        $vars = [
            'page_title' => config('constants.email_template.page_title'),
            'emailTemplate' => $emailTemplate
        ];

        return view('users.admin.email-template.edit', $vars);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  EmailTemplateRequest  $request
     * @param  int  $id
     * @return RedirectResponse
     */
    public function update(EmailTemplateRequest $request, $id): RedirectResponse
    {
        try {
            DB::beginTransaction();
            $emailTemplate = EmailTemplate::find($id);
            $emailTemplate->update($request->all());
            DB::commit();

            return redirect()
                ->route(routePrefix() . '.master-email-template.index')
                ->with('message', 'Email template updated.');
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception->getMessage());

            return back()
                ->withErrors(['errors' => 'Unable to update email template.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return mixed
     */
    public function destroy($id)
    {
        try {
            $emailTemplate = EmailTemplate::find($id);
            $emailTemplate->delete();
            return true;
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return sendJsonErrMsg('Unable to delete email template.');
        }
    }
}
