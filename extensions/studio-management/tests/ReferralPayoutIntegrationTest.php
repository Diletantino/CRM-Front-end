<?php
// Local installed CRM only. All database fixtures roll back, including failures.
require '/var/www/html/bootstrap.php';
use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\Utils\Config;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;
use Espo\Modules\StudioManagement\Tools\ShiftService;
$app = new Application();
$c = $app->getContainer();
if (!in_array(parse_url((string)$c->getByClass(Config::class)->get('siteUrl'), PHP_URL_HOST), ['localhost','127.0.0.1'], true)) {
    throw new RuntimeException('Disposable localhost only.');
}
$em = $c->getByClass(EntityManager::class);
$admin = $em->getRDBRepository('User')->where(['type'=>'admin','isActive'=>true])->findOne();
$c->getByClass(ApplicationUser::class)->setUser($admin);
$service = $app->getInjectableFactory()->create(ShiftService::class);
$checks=0; $files=[];
$assert=function(bool $condition, string $message) use (&$checks) {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$rollback=new RuntimeException('ROLLBACK_PAYOUT_FIXTURES');
try {
    $em->getTransactionManager()->run(function() use ($em,$admin,$service,$assert,$rollback,&$files) {
        $suffix=bin2hex(random_bytes(5));
        $create=function(string $type,array $data) use ($em) {
            $e=$em->getNewEntity($type);$e->set($data);$em->saveEntity($e);return $e;
        };
        $user=function(string $type,array $extra=[]) use ($create,$suffix) {
            return $create('User',array_merge(['userName'=>'payout-'.bin2hex(random_bytes(4)).'-'.$suffix,
                'firstName'=>'Payout','lastName'=>$type,'type'=>'regular','isActive'=>true,'smAccountType'=>$type,
                'smSharePercentOverride'=>0],$extra));
        };
        $referrer=$user('Model');
        $producer=$user('ProducerAdmin');
        $model=$user('Model',['smSiteData'=>[['site'=>'CB','login'=>'fixture','password'=>'fixture']]]);
        $run=function(string $mode,int $number) use ($user,$referrer,$producer,$model,$create,$em,$service,$admin,$assert,&$files) {
            $operator=$user('Operator',['smRecruiterId'=>$referrer->getId(),'smRecruiterRewardMode'=>$mode,
                'smRecruiterPercent'=>5,'smRecruiterFlatAmount'=>150]);
            $team=$create('Team',['name'=>'Payout test '.bin2hex(random_bytes(4)),'smProducerId'=>$producer->getId(),
                'smModelId'=>$model->getId(),'smOperatorId'=>$operator->getId()]);
            $last=null;
            for($i=1;$i<=$number;$i++) {
                $shift=$create('SmShift',['teamId'=>$team->getId(),'currency'=>'USD']);
                $proof=$em->getNewEntity(Attachment::ENTITY_TYPE);
                $proof->setName('payout-proof.png')->setType('image/png')->setRole('Attachment')
                    ->setParent($shift)->setTargetField('screenshots')
                    ->setContents(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf2QAAAAASUVORK5CYII='));
                $em->saveEntity($proof);$files[]=$proof;
                $opened=$service->open($shift->getId());
                $service->finish($shift->getId());
                $sites=array_map(fn($r)=>array_merge((array)$r,['earning'=>'1000.00','offlineBonus'=>'0.00']),$opened->get('siteData'));
                $service->close($shift->getId(),$sites,[],'',$admin);
                $closed=$service->approve($shift->getId(),$sites,[],'',$admin);
                $expenses=$em->getRDBRepository('SmShiftExpense')->where(['shiftId'=>$closed->getId(),
                    'category'=>'Recruiter','userId'=>$referrer->getId()])->find();
                $amount=0;
                foreach($expenses as $expense)$amount+=(float)$expense->get('amount');
                $expected=$mode==='PercentPerShift'?50:($i===5?150:0);
                $assert((float)$amount===(float)$expected,$mode.' actual payment on shift '.$i);
                $last=[$closed,$sites];
            }
            if($mode==='FlatAfterFiveShifts') {
                $fresh=$em->getEntityById('User',$operator->getId());
                $assert((bool)$fresh->get('smRecruiterMilestonePaid'),'One-time milestone is persisted.');
            }
            return $last;
        };
        $run('PercentPerShift',1);
        [$last,$sites]=$run('FlatAfterFiveShifts',6);
        $service->revise($last->getId(),$sites,[],$admin);
        $assert($em->getRDBRepository('SmShiftExpense')->where(['category'=>'Recruiter',
            'userId'=>$referrer->getId(),'amount'=>150])->count()===1,'Revision does not repeat the flat payout.');
        throw $rollback;
    });
} catch(Throwable $e) {
    if($e!==$rollback)throw $e;
} finally {
    foreach($files as $file)$c->get('fileStorageManager')->unlink($file);
}
echo "Referral payouts: $checks checks passed; fixtures rolled back.\n";
