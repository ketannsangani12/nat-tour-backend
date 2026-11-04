@extends('layouts.default-2')

@section('main-content')
@php
$routePrefix = routePrefix();
@endphp

<div class="page-content">
    <div class="content-title">
        <h2><i class="fas fa-fw fa-user-shield title-icon"></i> Role Types</h2>
    </div>
    <div class="sub-nav">
        <a href="{{ route($routePrefix . '.role-types') }}" class="active"><i class="fas fa-bars sub-nav-icon"></i> Role Types</a>
        <span class="sub-nav-sep">|</span>
        <a href="{{ route($routePrefix . '.role.index') }}" class=""><i class="fas fa-bars sub-nav-icon"></i> Role List</a>
    </div>
    <div class="main-content">
        <form id="roleTypesForm" method="post" action="#">


            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Role <req>*</req></label>
                    <div class="d-flex align-items-start">
                        <div class="flex-grow-1 me-2">
                            <select name="role" id="role" class="form-select">
                                <option disabled selected>Please select</option>
                                @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">Please select Role.</div>
                        </div>
                        <div>
                            <button type="button" id="addRoleTypebtn" class="btn btn-primary addRoleTypebtn d-none">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <div id="roleTypeSection">
            </div>
            <div class="mt-3 text-end"><button type="button" class="btn btn-main" id="save-role-type">Save</button></div>
        </form>
    </div> {{-- /.main-content --}}
</div> {{-- /.page-content --}}
@endsection

@section('js-custom')
<script>
    var userType = "{{ session('userType')  }}";
    var routePrefixUrl = "{{ routePrefix() }}";
</script>
<script src="{{ asset('js/user-common/role-types-add-edit.js') }}"></script>
<script>
    $(document).ready(() => {
        const saveRoleTypeUrl = "{{ route(routePrefix().'.save-role-type') }}";
        const fetchRoleTypesUrl = "{{ route(routePrefix().'.fetch-role-types') }}";
        const deleteRoleTypeUrl = "{{ route(routePrefix().'.delete-role-type',['id' => ':id']) }}";
        const roleTypesAddEdit = new RoleTypesAddEdit({
            saveRoleTypeUrl,
            fetchRoleTypesUrl,
            deleteRoleTypeUrl,
            userType
        });
    });
</script>
@endsection