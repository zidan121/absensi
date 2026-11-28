<?php
namespace App\Controllers;
use RuntimeException;
use Throwable;
class SditBackup extends BaseController
{
    private function sditTables(): array
    {
        $tables = array_values(array_filter(db_connect()->listTables(), static fn($table) => str_starts_with($table, 'sdit_')));
        sort($tables);
        return $tables;
    }
    public function index()
    {
        $db=db_connect(); $tables=[];
        foreach($this->sditTables() as $table) $tables[]=['name'=>$table,'count'=>(int)$db->table($table)->countAllResults()];
        return view('sdit/backup',['title'=>'Backup & Restore SDIT','tables'=>$tables]);
    }
    public function download()
    {
        $db=db_connect();
        $payload=['format'=>'neuro-absensi-sdit-backup','version'=>1,'created_at'=>date('c'),'tables'=>[]];
        foreach($this->sditTables() as $table) $payload['tables'][$table]=$db->table($table)->get()->getResultArray();
        $json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        if($json===false) return redirect()->to('/sdit/backup')->with('error','Data SDIT gagal disiapkan untuk backup.');
        $filename='Backup-SDIT-'.date('Ymd-His').'.json';
        return $this->response->setHeader('Content-Type','application/json; charset=utf-8')->setHeader('Content-Disposition','attachment; filename="'.$filename.'"')->setBody($json);
    }
    public function restore()
    {
        $file=$this->request->getFile('backup_file');
        if(!$file||!$file->isValid()||strtolower($file->getExtension())!=='json') return redirect()->to('/sdit/backup')->with('error','Pilih file backup SDIT JSON yang valid.');
        if($file->getSizeByUnit('mb')>200) return redirect()->to('/sdit/backup')->with('error','Ukuran file maksimal 200 MB.');
        if($this->request->getPost('confirm_merge')!=='1') return redirect()->to('/sdit/backup')->with('error','Centang konfirmasi sebelum restore.');
        $raw=file_get_contents($file->getTempName()); $backup=$raw===false?null:json_decode($raw,true);
        if(!is_array($backup)||($backup['format']??'')!=='neuro-absensi-sdit-backup'||(int)($backup['version']??0)!==1||!is_array($backup['tables']??null)) return redirect()->to('/sdit/backup')->with('error','Format backup tidak dikenali atau file rusak.');
        $db=db_connect(); $allowed=$this->sditTables(); $inserted=0; $skipped=0; $db->transBegin();
        try {
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            foreach($backup['tables'] as $table=>$rows) {
                if(!is_string($table)||!str_starts_with($table,'sdit_')||!in_array($table,$allowed,true)||!is_array($rows)) throw new RuntimeException('Backup memuat tabel yang bukan bagian dari SDIT.');
                $columns=$db->getFieldNames($table);
                foreach($rows as $row) {
                    if(!is_array($row)||array_diff(array_keys($row),$columns)) throw new RuntimeException('Struktur data tabel '.$table.' tidak sesuai.');
                    $builder=$db->table($table); $builder->ignore(true);
                    if($builder->insert($row)) $inserted++; else $skipped++;
                }
            }
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            if($db->transStatus()===false) throw new RuntimeException('Database menolak proses restore.');
            $db->transCommit();
            return redirect()->to('/sdit/backup')->with('success',$inserted.' baris ditambahkan; '.$skipped.' baris dilewati. Data lama tidak ditimpa.');
        } catch(Throwable $e) {
            $db->query('SET FOREIGN_KEY_CHECKS=1'); $db->transRollback();
            log_message('error','SDIT restore failed: {message}',['message'=>$e->getMessage()]);
            return redirect()->to('/sdit/backup')->with('error','Restore dibatalkan: '.$e->getMessage());
        }
    }
}