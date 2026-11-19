@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>@endif
@if($errors->any())<ul role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
