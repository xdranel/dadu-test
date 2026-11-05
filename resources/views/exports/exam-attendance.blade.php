<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir Ujian - {{ $schedule->course->course_code ?? '' }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .page {
            padding: 10px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
        }
        .info-label {
            width: 100px;
        }
        .info-value {
            font-weight: bold;
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .student-table th, .student-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 11px;
        }
        .student-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .text-center {
            text-align: center;
        }
        .col-no {
            width: 30px;
        }
        .col-nim {
            width: 100px;
        }
        .col-ttd {
            width: 150px;
        }
        .footer-table {
            width: 100%;
            margin-top: 15px;
            font-size: 10px;
        }
        .footer-table td {
            padding: 2px 0;
        }
    </style>
</head>
<body>
    @php
        $perPage = 25;
        $chunks = $students->chunk($perPage);
        $totalStudents = $students->count();
        $totalPages = $chunks->count() ?: 1;

        $examDate = $schedule->date 
            ? \Carbon\Carbon::parse($schedule->date)->locale('id')->isoFormat('DD MMMM YYYY') 
            : '-';
        
        $startTime = $schedule->start_time 
            ? \Carbon\Carbon::parse($schedule->start_time)->format('H:i') 
            : '-';
            
        $endTime = $schedule->end_time 
            ? \Carbon\Carbon::parse($schedule->end_time)->format('H:i') 
            : '-';

        $roomName = $schedule->room ? $schedule->room->name : ($schedule->room_1 ?? '-');
        if ($schedule->participant_division === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && !empty($schedule->room_2)) {
            $room2Name = $schedule->room2 ? $schedule->room2->name : $schedule->room_2;
            $roomName .= ' & ' . $room2Name;
        }

        $lecturerName = $schedule->lecturer ? $schedule->lecturer->name : ($schedule->lecturer_code ?? '-');
        if (!empty($schedule->lecturer_code_2)) {
            $lecturer2Name = $schedule->lecturer2 ? $schedule->lecturer2->name : $schedule->lecturer_code_2;
            $lecturerName .= ' & ' . $lecturer2Name;
        }
    @endphp

    @if($totalStudents === 0)
        <div class="page">
            <h2 style="text-align:center; font-size:16px; font-weight:bold;">
                DAFTAR HADIR UJIAN
            </h2>
            <hr style="border: 2px solid black; margin-bottom: 12px;">

            <table class="info-table">
                <tr>
                    <td style="width: 50%;">
                        <table style="width: 100%;">
                            <tr>
                                <td class="info-label">Mata Kuliah</td>
                                <td style="width: 8px;">:</td>
                                <td class="info-value">{{ $schedule->course->course_code ?? '-' }} - {{ $schedule->course->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Program Studi</td>
                                <td>:</td>
                                <td class="info-value">{{ $schedule->course->study_program ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Kelas</td>
                                <td>:</td>
                                <td class="info-value">{{ $schedule->course_class ?? $schedule->course->class ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">SKS</td>
                                <td>:</td>
                                <td class="info-value">{{ $schedule->course->credits ?? '-' }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%;">
                        <table style="width: 100%;">
                            <tr>
                                <td class="info-label">Tanggal</td>
                                <td style="width: 8px;">:</td>
                                <td class="info-value">{{ $examDate }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Waktu</td>
                                <td>:</td>
                                <td class="info-value">{{ $startTime }} - {{ $endTime }} WIB</td>
                            </tr>
                            <tr>
                                <td class="info-label">Ruangan</td>
                                <td>:</td>
                                <td class="info-value">{{ $roomName }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Dosen</td>
                                <td>:</td>
                                <td class="info-value">{{ $lecturerName }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="text-align: center; margin: 50px 0; font-size: 12px; color: #555;">
                Tidak ada data peserta ujian untuk jadwal ini.
            </div>

            <table class="footer-table">
                <tr>
                    <td style="width: 120px;">Jumlah Peserta</td>
                    <td>: 0 Mahasiswa</td>
                </tr>
                <tr>
                    <td>Dicetak pada</td>
                    <td>: {{ $printedAt }} WIB</td>
                </tr>
            </table>
        </div>
    @else
        @foreach($chunks as $pageIndex => $chunk)
            <div class="page" style="{{ !$loop->last ? 'page-break-after: always;' : '' }}">
                <h2 style="text-align:center; font-size:16px; font-weight:bold;">
                    DAFTAR HADIR UJIAN
                </h2>
                <hr style="border: 2px solid black; margin-bottom: 12px;">

                <table class="info-table">
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tr>
                                    <td class="info-label">Mata Kuliah</td>
                                    <td style="width: 8px;">:</td>
                                    <td class="info-value">{{ $schedule->course->course_code ?? '-' }} - {{ $schedule->course->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Program Studi</td>
                                    <td>:</td>
                                    <td class="info-value">{{ $schedule->course->study_program ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Kelas</td>
                                    <td>:</td>
                                    <td class="info-value">{{ $schedule->course_class ?? $schedule->course->class ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">SKS</td>
                                    <td>:</td>
                                    <td class="info-value">{{ $schedule->course->credits ?? '-' }}</td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tr>
                                    <td class="info-label">Tanggal</td>
                                    <td style="width: 8px;">:</td>
                                    <td class="info-value">{{ $examDate }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Waktu</td>
                                    <td>:</td>
                                    <td class="info-value">{{ $startTime }} - {{ $endTime }} WIB</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Ruangan</td>
                                    <td>:</td>
                                    <td class="info-value">{{ $roomName }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Dosen</td>
                                    <td>:</td>
                                    <td class="info-value">{{ $lecturerName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="student-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-nim">NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th class="col-ttd">Tanda Tangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($chunk as $index => $student)
                            <tr>
                                <td class="text-center">{{ ($pageIndex * $perPage) + $index + 1 }}</td>
                                <td class="text-center">{{ $student->student_number }}</td>
                                <td>{{ $student->name }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="footer-table">
                    <tr>
                        <td style="width: 120px;">Jumlah Peserta</td>
                        <td>: {{ $totalStudents }} Mahasiswa</td>
                    </tr>
                    <tr>
                        <td>Dicetak pada</td>
                        <td>: {{ $printedAt }} WIB</td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endif
</body>
</html>
