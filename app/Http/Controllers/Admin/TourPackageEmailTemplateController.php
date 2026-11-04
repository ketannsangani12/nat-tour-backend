<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourPackageEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\TourPackage;
use App\Models\TourPackageEmailTemplate;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\DataTables;

class TourPackageEmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return mixed
     */
    public function index(TourPackage $tourPackage)
    {
        $vars = [
            'page_title' => config('constants.tour_package_email_template.page_title'),
            'tourPackage' => $tourPackage
        ];

        return view('users.admin.email-template.tour-package.index', $vars);
    }

    /**
     * Json response for DataTable.
     *
     * @param Request $request
     * @param TourPackage $tourPackage
     * @return JsonResponse
     */
    public function indexAHR(Request $request, TourPackage $tourPackage): JsonResponse
    {
        $query = TourPackageEmailTemplate::with(['tourPackage', 'emailTemplate'])
            ->where('tour_package_id', $tourPackage->id);

        return Datatables::of($query)
            ->addIndexColumn()
            ->addColumn('name', function ($tourPackageEmailTemplate) {
                return $tourPackageEmailTemplate->emailTemplate->name;
            })
            ->editColumn('action', function ($tourPackageEmailTemplate) {
                $routePrefix = routePrefix();

                if (userType() == 'Tour Operator' && Session::get('roleNames')->contains("id", "4")) {
                    return $this->getEditButton(route($routePrefix . '.email-template.edit', ['tour_package' => $tourPackageEmailTemplate->tour_package_id, 'email_template' => $tourPackageEmailTemplate->id]));
                } else {
                    return $this->getEditButton(route($routePrefix . '.email-template.edit', ['tour_package' => $tourPackageEmailTemplate->tour_package_id, 'email_template' => $tourPackageEmailTemplate->id]))
                        . $this->getDeleteButton(route($routePrefix . '.email-template.destroy', ['tour_package' => $tourPackageEmailTemplate->tour_package_id, 'email_template' => $tourPackageEmailTemplate->id]));
                }
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param TourPackage $tourPackage
     * @return mixed
     */
    public function create(TourPackage $tourPackage)
    {
        $vars = [
            'page_title' => config('constants.tour_package_email_template.page_title'),
            'emailTemplates' => EmailTemplate::all()->keyBy('id'),
            'tourPackage' => $tourPackage
        ];

        return view('users.admin.email-template.tour-package.create', $vars);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param TourPackageEmailTemplateRequest  $request
     * @param TourPackage $tourPackage
     * @return RedirectResponse
     */
    public function store(TourPackageEmailTemplateRequest $request, TourPackage $tourPackage): RedirectResponse
    {
        try {
            DB::beginTransaction();
            $input = array_merge($request->all(), ['tour_package_id' => $tourPackage->id]);
            TourPackageEmailTemplate::create($input);
            DB::commit();

            if (session('userType') == 'Admin') {
                $routePrefix = 'admin.';
            } else {
                $routePrefix = '';
            }

            return redirect()
                ->route($routePrefix . 'tour-operator.email-template.index', $tourPackage->id)
                ->with('message', 'Email template added.');
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception->getMessage());

            return back()
                ->withErrors(['errors' => 'Unable to add email template for the package.'])
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param TourPackage $tourPackage
     * @param int $id
     * @return mixed
     */
    public function edit(TourPackage $tourPackage, $id)
    {
        $tourPackageEmailTemplate = TourPackageEmailTemplate::findOrFail($id);

        $vars = [
            'page_title' => config('constants.tour_package_email_template.page_title'),
            'tourPackageEmailTemplate' => $tourPackageEmailTemplate,
            'emailTemplates' => EmailTemplate::all()->keyBy('id'),
            'tourPackage' => $tourPackage
        ];

        return view('users.admin.email-template.tour-package.edit', $vars);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  TourPackageEmailTemplateRequest  $request
     * @param  TourPackage  $tourPackage
     * @param  int  $id
     * @return RedirectResponse
     */
    public function update(TourPackageEmailTemplateRequest $request, TourPackage $tourPackage, $id): RedirectResponse
    {
        try {
            DB::beginTransaction();
            $tourPackageEmailTemplate = TourPackageEmailTemplate::find($id);
            $tourPackageEmailTemplate->update($request->all());
            DB::commit();

            if (session('userType') == 'Admin') {
                $routePrefix = 'admin.';
            } else {
                $routePrefix = '';
            }

            return redirect()
                ->route($routePrefix . 'tour-operator.email-template.index', $tourPackage->id)
                ->with('message', 'Email template updated.');
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception->getMessage());

            return back()
                ->withErrors(['errors' => 'Unable to update email template for the package.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return mixed
     */
    public function destroy(TourPackage $tourPackage, $id): mixed
    {
        try {
            $tourPackageEmailTemplate = TourPackageEmailTemplate::find($id);
            $tourPackageEmailTemplate->delete();

            return true;
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return sendJsonErrMsg('Unable to delete email template for the package.');
        }
    }
}
