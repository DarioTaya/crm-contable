<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extracto Bancario BN - {{ $cliente->razon_social }} - {{ $extracto['anio'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #b91c1c;
            padding-bottom: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .logo-bn {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-badge {
            background: #b91c1c;
            color: white;
            font-weight: 900;
            font-size: 20px;
            padding: 6px 12px;
            border-radius: 8px;
            letter-spacing: -1px;
        }
        .title-block h1 {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .title-block p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: #64748b;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .meta-col p {
            margin: 2px 0;
            font-size: 11px;
        }
        .meta-col strong {
            color: #0f172a;
        }
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        .kpi-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            background: #fff;
        }
        .kpi-card.highlight {
            border-color: #0d9488;
            background: #f0fdfa;
        }
        .kpi-card .label {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 800;
            color: #64748b;
        }
        .kpi-card .val {
            font-size: 14px;
            font-weight: 900;
            font-family: monospace;
            margin-top: 3px;
            color: #0f172a;
        }
        .kpi-card.highlight .val {
            color: #0f766e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 16px;
        }
        th {
            background: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 6px 8px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 9px;
            border-bottom: 1px solid #cbd5e1;
        }
        td {
            padding: 5px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        tr.initial-row {
            background: #f8fafc;
            font-weight: 700;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .deposito { color: #047857; font-weight: 700; }
        .retiro { color: #b91c1c; font-weight: 700; }
        .saldo { font-weight: 800; color: #0f172a; }
        tfoot tr td {
            background: #f8fafc;
            border-top: 2px solid #cbd5e1;
            font-weight: 900;
            font-size: 11px;
            padding: 7px 8px;
        }
        .footer-signatures {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            page-break-inside: avoid;
        }
        .sign-box {
            text-align: center;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            font-size: 10px;
        }
        .print-btn-bar {
            background: #0f172a;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 15px;
        }
        .print-btn {
            background: #dc2626;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
        }
        @media print {
            .print-btn-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="print-btn-bar">
        <div>
            <strong>EXTRACTO OFICIAL BANCO DE LA NACIÓN - SISTEMA SPOT</strong>
            <span style="opacity: 0.7; margin-left: 10px;">{{ $cliente->razon_social }} (RUC: {{ $cliente->ruc }})</span>
        </div>
        <button class="print-btn" onclick="window.print()">🖨️ Imprimir Extracto</button>
    </div>

    <div class="header">
        <div class="logo-bn">
            <div class="logo-badge">BN</div>
            <div class="title-block">
                <h1>BANCO DE LA NACIÓN</h1>
                <p>SISTEMA DE PAGO DE OBLIGACIONES TRIBUTARIAS (SPOT - D. LEG. 940) • CUENTA CONVENCIONAL</p>
            </div>
        </div>
        <div style="text-align: right; font-size: 10px; color: #64748b;">
            <p style="margin: 0;"><strong>Fecha de Emisión:</strong> {{ now()->format('d/m/Y H:i') }}</p>
            <p style="margin: 2px 0 0 0;"><strong>Estudio:</strong> {{ $estudio?->nombre ?? 'Estudio Contable' }}</p>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-col">
            <p><strong>Titular / Razón Social:</strong> {{ $cliente->razon_social }}</p>
            <p><strong>Número de RUC:</strong> <span class="font-mono">{{ $cliente->ruc }}</span> &nbsp;|&nbsp; <strong>Régimen:</strong> {{ $cliente->regimen_tributario?->etiquetaCompleta() ?? 'General' }}</p>
            <p><strong>N° Cuenta Detracciones BN:</strong> <strong class="font-mono">{{ $cliente->credencial?->cuenta_detracciones ?? 'No registrada' }}</strong></p>
        </div>
        <div class="meta-col text-right">
            <p><strong>Ejercicio Fiscal:</strong> {{ $extracto['anio'] }}</p>
            <p><strong>Periodo Consultado:</strong> {{ $extracto['mes_nombre'] ?? 'Todo el año' }}</p>
            <p><strong>Moneda:</strong> Soles (S/)</p>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="label">Saldo Inicial</div>
            <div class="val">S/ {{ number_format($extracto['saldo_inicial'], 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Total Depósitos (+)</div>
            <div class="val" style="color: #047857;">S/ {{ number_format($extracto['total_depositos'], 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Total Retiros (-)</div>
            <div class="val" style="color: #b91c1c;">S/ {{ number_format($extracto['total_retiros'], 2) }}</div>
        </div>
        <div class="kpi-card highlight">
            <div class="label">Saldo Final Disponible</div>
            <div class="val">S/ {{ number_format($extracto['saldo_final'], 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">N°</th>
                <th style="width: 65px;">Fecha</th>
                <th style="width: 90px;">Operación / Const.</th>
                <th style="width: 75px;">Comprobante</th>
                <th>Descripción / Concepto</th>
                <th style="width: 140px;">Adquiriente</th>
                <th class="text-right" style="width: 80px;">Depósito (+)</th>
                <th class="text-right" style="width: 80px;">Retiro (-)</th>
                <th class="text-right" style="width: 85px;">Saldo Resultante</th>
            </tr>
        </thead>
        <tbody>
            <tr class="initial-row">
                <td class="text-center font-mono">—</td>
                <td class="font-mono">{{ $extracto['mes'] !== null ? '01/'.sprintf('%02d', $extracto['mes']).'/'.$extracto['anio'] : '01/01/'.$extracto['anio'] }}</td>
                <td class="font-mono">—</td>
                <td class="font-mono">—</td>
                <td><strong>SALDO INICIAL / APERTURA</strong></td>
                <td>—</td>
                <td class="text-right font-mono">—</td>
                <td class="text-right font-mono">—</td>
                <td class="text-right font-mono saldo">S/ {{ number_format($extracto['saldo_inicial'], 2) }}</td>
            </tr>

            @forelse ($extracto['movimientos'] as $idx => $mov)
                <tr>
                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                    <td class="font-mono">{{ $mov->fecha->format('d/m/Y') }}</td>
                    <td class="font-mono" style="font-size: 9px;">
                        {{ $mov->numero_constancia ? 'C: '.$mov->numero_constancia : ($mov->numero_operacion ? 'Op: '.$mov->numero_operacion : '—') }}
                    </td>
                    <td class="font-mono">{{ $mov->comprobante_referencia ?? '—' }}</td>
                    <td>
                        {{ $mov->descripcion }}
                    </td>
                    <td style="font-size: 9px;">
                        {{ $mov->adquiriente_razon_social ?? ($mov->adquiriente_ruc ? 'RUC '.$mov->adquiriente_ruc : '—') }}
                    </td>
                    <td class="text-right font-mono deposito">
                        {{ $mov->isDeposito() ? 'S/ '.number_format((float) $mov->monto, 2) : '—' }}
                    </td>
                    <td class="text-right font-mono retiro">
                        {{ $mov->isRetiro() ? 'S/ '.number_format((float) $mov->monto, 2) : '—' }}
                    </td>
                    <td class="text-right font-mono saldo">
                        S/ {{ number_format((float) ($mov->saldo_acumulado ?? 0), 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #64748b;">
                        No se registraron movimientos en este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right">TOTALES Y SALDO NETO RESULTANTE:</td>
                <td class="text-right font-mono deposito">S/ {{ number_format($extracto['total_depositos'], 2) }}</td>
                <td class="text-right font-mono retiro">S/ {{ number_format($extracto['total_retiros'], 2) }}</td>
                <td class="text-right font-mono saldo">S/ {{ number_format($extracto['saldo_final'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer-signatures">
        <div class="sign-box">
            <p style="margin: 0; font-weight: bold;">{{ $cliente->razon_social }}</p>
            <p style="margin: 2px 0 0 0; color: #64748b;">RUC: {{ $cliente->ruc }} • Titular</p>
        </div>
        <div class="sign-box">
            <p style="margin: 0; font-weight: bold;">{{ $estudio?->nombre ?? 'Estudio Contable' }}</p>
            <p style="margin: 2px 0 0 0; color: #64748b;">Contador Público Colegiado (C.P.C.)</p>
        </div>
    </div>

</body>
</html>
