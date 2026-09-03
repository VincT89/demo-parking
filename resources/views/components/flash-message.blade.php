@if (session('success'))
    <div class="pm-flash-success pm-animate">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="pm-flash-error pm-animate">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="pm-flash-error pm-animate">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif