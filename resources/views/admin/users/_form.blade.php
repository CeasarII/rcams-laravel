{{-- Expects: $roles, $departments, $companies, $vessels, $user (nullable) --}}

@php
    $selectedRoleIds = old('roles', optional($user ?? null)->roles?->pluck('id')->toArray() ?? []);
    $selectedVesselIds = old('vessel_ids', optional($user ?? null)->vessels?->pluck('id')->toArray() ?? []);
    $selectedDepartmentIds = old('department_ids', optional($user ?? null)->departments?->pluck('id')->toArray() ?? []);
    $oeRoleId = $roles->firstWhere('code', 'OE')?->id;
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Employee No.</label>
        <input type="text" name="employee_no" class="form-control" value="{{ old('employee_no', $user->employee_no ?? '') }}" required>
    </div>

    <div class="col-md-8">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $user->full_name ?? '') }}" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Company</label>
        <select name="company_id" class="form-select" required>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected(old('company_id', $user->company_id ?? '') == $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Department</label>
        <select name="department_id" class="form-select" required>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id ?? '') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Password @if($user ?? null)<span class="text-muted small">(leave blank to keep current)</span>@endif</label>
        <input type="password" name="password" class="form-control" {{ ($user ?? null) ? '' : 'required' }}>
    </div>

    <div class="col-md-6">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirmation" class="form-control" {{ ($user ?? null) ? '' : 'required' }}>
    </div>
</div>

<hr class="my-4">

<h2 class="h5">Roles</h2>
<div class="row">
    @foreach ($roles as $role)
        <div class="col-md-4 form-check mb-2">
            <input type="checkbox" name="roles[]" value="{{ $role->id }}" id="role-{{ $role->id }}"
                   class="form-check-input role-checkbox" data-role-code="{{ $role->code }}"
                   @checked(in_array($role->id, $selectedRoleIds))>
            <label for="role-{{ $role->id }}" class="form-check-label">{{ $role->label }}</label>
        </div>
    @endforeach
</div>

<div id="scope-section" class="mt-4" style="display: none;">
    <h2 class="h5">Vessel &amp; Scope Assignment</h2>
    <p class="text-muted small">
        <strong>Requestor:</strong> the vessels this user files Sea Base requests for. One vessel is filled in
        automatically on the fund request; several give a pick-list; none means Shore Base only.
        <strong>OE:</strong> the vessels (Sea Base) and departments (Shore Base) they may validate.
        An OE with no assignment can't validate or be notified of anything.
    </p>

    <div class="row g-3">
        <div class="col-md-6" id="vessel-col">
            <label class="form-label">Vessels</label>
            <select name="vessel_ids[]" class="form-select" multiple size="6">
                @foreach ($vessels as $vessel)
                    <option value="{{ $vessel->id }}" @selected(in_array($vessel->id, $selectedVesselIds))>{{ $vessel->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6" id="oe-department-col">
            <label class="form-label">Departments (OE only)</label>
            <select name="department_ids[]" class="form-select" multiple size="6">
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(in_array($department->id, $selectedDepartmentIds))>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<script>
(function () {
    const oe = document.querySelector('.role-checkbox[data-role-code="OE"]');
    const requestor = document.querySelector('.role-checkbox[data-role-code="REQUESTOR"]');
    const section = document.getElementById('scope-section');
    const deptCol = document.getElementById('oe-department-col');

    function toggle() {
        const isOe = oe && oe.checked;
        const isRequestor = requestor && requestor.checked;
        section.style.display = isOe || isRequestor ? '' : 'none';
        deptCol.style.display = isOe ? '' : 'none';
    }

    [oe, requestor].forEach(function (box) {
        if (box) box.addEventListener('change', toggle);
    });
    toggle();
})();
</script>
