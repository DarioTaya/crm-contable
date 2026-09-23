@if ($fechas === [])
    <p class="mt-3 text-xs text-gray-400">Sin fechas en el cronograma de este periodo.</p>
@else
    <div class="mt-3 grid w-full grid-cols-6 gap-2">
        @foreach ($fechas as $vencimiento)
            <div @class([
                'flex flex-col items-center justify-center rounded-lg p-2 text-center',
                'col-span-2 border border-emerald-200' => $vencimiento['digito'] === 'BC',
                'border border-gray-100 bg-gray-50 text-gray-400 opacity-70' => $vencimiento['estado'] === 'pasado' && $vencimiento['digito'] !== 'BC',
                'bg-gray-50 text-gray-400 opacity-70' => $vencimiento['estado'] === 'pasado' && $vencimiento['digito'] === 'BC',
                'bg-white font-bold text-emerald-700 shadow-md ring-2 ring-emerald-500' => $vencimiento['estado'] === 'hoy',
                'border border-gray-200 bg-white text-gray-700' => $vencimiento['estado'] === 'futuro' && $vencimiento['digito'] !== 'BC',
                'bg-white text-gray-700' => $vencimiento['estado'] === 'futuro' && $vencimiento['digito'] === 'BC',
            ]) @if ($vencimiento['digito'] === 'BC') title="Buenos Contribuyentes" @endif>
                <span class="text-lg leading-none">{{ $vencimiento['digito'] }}</span>
                @if ($conSire && $vencimiento['sire'])
                    <span class="mt-1.5 text-[10px] leading-tight">SIRE {{ $vencimiento['sire'] }}</span>
                @endif
                @if ($vencimiento['pdt'])
                    <span class="mt-1 text-[10px] leading-tight">{{ $conSire ? 'PDT '.$vencimiento['pdt'] : $vencimiento['pdt'] }}</span>
                @endif
            </div>
        @endforeach
    </div>
@endif
