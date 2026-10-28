<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Branches\CreateBranch;
use App\Actions\Invitations\AcceptInvitation;
use App\Enums\ActorType;
use App\Enums\AppointmentChangeAction;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PetSex;
use App\Enums\PetSize;
use App\Enums\PlatformAdminRole;
use App\Enums\ServiceGroup;
use App\Enums\TenantStatus;
use App\Enums\VaccinationStatus;
use App\Enums\VaccineKind;
use App\Enums\VisitItemType;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\Breed;
use App\Models\CareProtocol;
use App\Models\CashDrawer;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\MedicalEntry;
use App\Models\MedicalRecord;
use App\Models\OnlineBookingRate;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Service;
use App\Models\ServicePriceRule;
use App\Models\Species;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\Visit;
use App\Models\VisitItem;
use App\Platform\Actions\ProvisionTenant;
use App\Platform\Data\NewShop;
use App\Platform\Data\ProvisionedShop;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Demo dataset of docs/design/design.md section 9 (local / demo only).
 * "Today" is the real current day in the shop's time zone; every DATETIME is stored in UTC
 * (08:00 in Asia/Ho_Chi_Minh = 01:00 UTC). Shop-local TIME / DATE columns stay local.
 *
 * Shops are created the only way they can be: ProvisionTenant (as admin@pawbook.test, no mail);
 * the seeder then accepts each owner's invitation on their behalf.
 * Roles follow design.md section 9 (titles are display labels only; permissions come from roles).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    private const TZ = 'Asia/Ho_Chi_Minh';

    private TenantContext $context;

    /** Local midnight of today in the shop's time zone. */
    private CarbonImmutable $today;

    /** @var array<array-key, int> */
    private array $species = [];

    /** @var array<array-key, int> */
    private array $breeds = [];

    private PlatformAdmin $admin;

    /** @var list<array{0: Tenant, 1: array<string, mixed>}> */
    private array $states = [];

    /** @var array<string, Staff> */
    private array $staff = [];

    /** @var array<string, Service> */
    private array $services = [];

    /** @var array<string, Customer> */
    private array $customers = [];

    /** @var array<string, Pet> */
    private array $pets = [];

    public function run(TenantContext $context): void
    {
        $this->context = $context;
        $this->today = CarbonImmutable::now(self::TZ)->startOfDay();
        $this->species = Species::query()->pluck('id', 'code')->map(fn ($id): int => (int) $id)->all();
        $this->breeds = Breed::query()->pluck('id', 'name')->map(fn ($id): int => (int) $id)->all();

        // Platform admins without 2FA: they can sign in only where TwoFactor::canSkip() holds
        // (APP_ENV local / demo + PLATFORM_SKIP_2FA); anywhere else they must set up TOTP first.
        $this->admin = PlatformAdmin::query()->create([
            'name' => 'Nguyễn Văn A', 'email' => 'admin@pawbook.test', 'password' => self::PASSWORD, 'role' => PlatformAdminRole::SuperAdmin,
        ]);
        PlatformAdmin::query()->create([
            'name' => 'Lê Thị Hỗ Trợ', 'email' => 'support@pawbook.test', 'password' => self::PASSWORD, 'role' => PlatformAdminRole::Support,
        ]);

        $this->pawHouse();
        $this->meowClinic();
        $this->smallShop('Happy Tails', 'happy-tails', 'starter', TenantStatus::Trial, $this->at('23:59', 5), 'HT', 'Trịnh Văn Phúc', 'phuc@happytails.test');
        $this->smallShop('Bé Cún Spa', 'be-cun-spa', 'starter', TenantStatus::Suspended, $this->at('23:59', -10), 'BC', 'Lâm Thị Ngọc', 'ngoc@becun.test', 'Chưa thanh toán');

        foreach ($this->states as [$tenant, $state]) {
            $tenant->forceFill($state)->save();
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    // ---------------------------------------------------------------- Paw House

    private function pawHouse(): void
    {
        $shop = $this->provision('Paw House Spa & Vet', 'paw-house', 'pro', TenantStatus::Active, $this->at('23:59', 364),
            ['Q3', 'CN Quận 3', '123 Võ Văn Tần, Phường 6, Quận 3, TP.HCM', '+842839300001'],
            ['Trần Minh Khoa', 'khoa@pawhouse.test', '+84903000111'],
        );

        $this->context->runAs($shop->tenant, function () use ($shop): void {
            TenantSetting::query()->update(['site_intro' => 'Spa & phòng khám thú cưng tại Quận 3 và Quận 7.']);

            $q3 = Branch::query()->where('code', 'Q3')->firstOrFail();
            $q7 = $this->branch('Q7', 'CN Quận 7', '45 Nguyễn Thị Thập, Tân Phú, Quận 7, TP.HCM', '+842837700002');

            $closure = $this->today->setDate($this->today->year, 9, 2);
            BranchClosure::query()->create([
                'branch_id' => $q3->id,
                'date' => ($closure->lt($this->today) ? $closure->addYear() : $closure)->toDateString(),
                'reason' => 'Nghỉ lễ Quốc khánh',
            ]);

            OnlineBookingRate::query()->create(['branch_id' => $q3->id, 'service_group' => ServiceGroup::Medical, 'ratio' => '0.333']);
            OnlineBookingRate::query()->create(['branch_id' => $q3->id, 'service_group' => ServiceGroup::Grooming, 'ratio' => '0.500']);

            $this->pawHouseStaff($shop, $q3, $q7);
            $this->pawHouseServices();
            $this->pawHouseCustomers();
            $this->pawHouseCare();
            $this->pawHouseToday($q3);
        });
    }

    private function pawHouseStaff(ProvisionedShop $shop, Branch $q3, Branch $q7): void
    {
        $people = [
            // key => [name, email (null = no account), title, color, primary, extra branches, roles]
            'khoa' => ['Trần Minh Khoa', 'khoa@pawhouse.test', 'Chủ shop · Bác sĩ', '#0D9488', $q3, [$q7], ['owner', 'vet']],
            'ha' => ['BS. Lê Thu Hà', 'ha@pawhouse.test', 'Bác sĩ', '#8B5CF6', $q3, [], ['vet']],
            'tri' => ['BS. Phan Đức Trí', 'tri@pawhouse.test', 'Bác sĩ', '#22C55E', $q3, [], ['vet']],
            'ngocanh' => ['Nguyễn Ngọc Anh', 'ngocanh@pawhouse.test', 'Nhân viên spa', '#F97362', $q3, [], ['groomer']],
            'bao' => ['Phạm Quốc Bảo', 'bao@pawhouse.test', 'Nhân viên spa', '#0EA5E9', $q3, [$q7], ['groomer']],
            'mai' => ['Võ Thị Mai', 'mai@pawhouse.test', 'Lễ tân · Thu ngân', '#EAB308', $q3, [], ['receptionist', 'cashier']],
            'hung' => ['Đỗ Văn Hùng', 'hung@pawhouse.test', 'Quản lý chi nhánh', '#EC4899', $q7, [], ['branch_manager']],
            'kim' => ['Lý Thị Kim', 'kim@pawhouse.test', 'Nhân viên kho', '#64748B', $q3, [], ['inventory']],
            'tu' => ['Lê Văn Tư', null, 'Bảo vệ', null, $q3, [], []],
        ];

        foreach ($people as $key => [$name, $email, $title, $color, $primary, $extra, $roles]) {
            if ($key === 'khoa') {
                // The owner joins through their invitation (owner role, every branch) + `vet`.
                $this->staff[$key] = $this->owner($shop, $name, (string) $email, $title, (string) $color, ['vet']);

                continue;
            }

            $this->staff[$key] = $this->member($primary, $extra, $name, $title, $color, $email === null ? null : $this->user($name, $email), $roles);
        }

        $this->staff['khoa']->forceFill(['license_no' => 'CCHN-0123/HCM', 'qualifications' => 'Bác sĩ thú y, 12 năm kinh nghiệm'])->save();

        // One person on duty = one slot of one group, never both; no overlapping slots across branches.
        $medical = ServiceGroup::Medical;
        $grooming = ServiceGroup::Grooming;
        $roster = [
            ['khoa', $q3, '08:00', '12:00', $medical],
            ['khoa', $q7, '13:00', '17:00', $medical],
            ['ha', $q3, '08:00', '17:00', $medical],
            ['tri', $q3, '08:00', '11:00', $medical], // split shift ("ca gãy")
            ['tri', $q3, '15:00', '20:00', $medical],
            ['ngocanh', $q3, '08:00', '17:00', $grooming],
            ['bao', $q3, '08:00', '15:00', $grooming],
            ['bao', $q7, '16:00', '20:00', $grooming],
            ['mai', $q3, '08:00', '17:00', null],
            ['hung', $q7, '08:00', '17:00', null],
            ['kim', $q3, '08:00', '17:00', null],
            ['tu', $q3, '12:00', '20:00', null],
        ];

        foreach ($roster as [$key, $branch, $start, $end, $group]) {
            foreach (range(1, 7) as $weekday) {
                StaffWorkingHour::query()->create([
                    'staff_id' => $this->staff[$key]->id,
                    'branch_id' => $branch->id,
                    'weekday' => $weekday,
                    'start_time' => $start.':00',
                    'end_time' => $end.':00',
                    'service_group' => $group,
                    'effective_from' => $this->today->subMonths(6)->toDateString(),
                ]);
            }
        }

        // Ngọc Anh is off this afternoon.
        StaffTimeOff::query()->create([
            'staff_id' => $this->staff['ngocanh']->id,
            'starts_at' => $this->at('13:00'),
            'ends_at' => $this->at('17:00'),
            'reason' => 'Nghỉ buổi chiều (việc gia đình)',
            'created_by' => $this->staff['khoa']->id,
        ]);
    }

    private function pawHouseServices(): void
    {
        $sml = fn (int $s, int $m, int $l, int $ps, int $pm, int $pl): array => [
            ['dog', PetSize::S, $s, $ps], ['dog', PetSize::M, $m, $pm], ['dog', PetSize::L, $l, $pl],
            ['cat', null, $s, $ps],
        ];

        $this->services = [
            'bath' => $this->service('SPA-TAM', 'Tắm + sấy', ServiceGroup::Grooming, 60, 15, 300_000, $sml(45, 60, 90, 200_000, 300_000, 400_000)),
            'trim' => $this->service('SPA-CAT', 'Cắt tỉa', ServiceGroup::Grooming, 60, 15, 275_000, $sml(45, 60, 90, 200_000, 275_000, 350_000)),
            'combo' => $this->service('CB01', 'Combo Cắt tỉa + tắm gội', ServiceGroup::Grooming, 105, 15, 500_000, $sml(75, 105, 150, 350_000, 500_000, 650_000), isCombo: true),
            'nails' => $this->service('SPA-MONG', 'Cắt móng + vệ sinh tai', ServiceGroup::Grooming, 20, 5, 80_000),
            'exam' => $this->service('KB01', 'Khám tổng quát', ServiceGroup::Medical, 30, 0, 150_000),
            'vaccine' => $this->service('TP01', 'Tiêm phòng', ServiceGroup::Medical, 15, 0, 250_000, [['dog', PetSize::L, null, 450_000], ['dog', PetSize::M, null, 350_000]]),
            'blood' => $this->service('XN01', 'Xét nghiệm máu', ServiceGroup::Medical, 20, 0, 300_000),
            'ultrasound' => $this->service('SA01', 'Siêu âm', ServiceGroup::Medical, 20, 0, 250_000),
        ];

        $this->services['combo']->comboItems()->attach([
            $this->services['trim']->id => ['tenant_id' => $this->services['combo']->tenant_id, 'sort_order' => 1],
            $this->services['bath']->id => ['tenant_id' => $this->services['combo']->tenant_id, 'sort_order' => 2],
        ]);
    }

    private function pawHouseCustomers(): void
    {
        $list = [
            // customer key => [name, phone, [pet key => [name, species, breed, sex, age in days, kg, size, extra]]]
            'lan' => ['Nguyễn Thị Lan', '0903123456', [
                'mochi' => ['Mochi', 'dog', 'Poodle', PetSex::Female, 3 * 365, 5.2, PetSize::S, ['allergies' => 'Dị ứng dầu gội có hương liệu']],
                'bo' => ['Bơ', 'cat', 'Mèo Anh lông ngắn', PetSex::Male, 2 * 365, 4.8, PetSize::M, []],
            ]],
            'nam' => ['Hoàng Văn Nam', '0912987654', [
                'lu' => ['Lu', 'dog', 'Golden Retriever', PetSex::Male, 5 * 365, 32, PetSize::L, ['behavior_notes' => 'Sợ máy sấy']],
            ]],
            'linh' => ['Đặng Mỹ Linh', '0938555222', [
                'sushi' => ['Sushi', 'dog', 'Corgi', PetSex::Female, 182, 9, PetSize::M, []],
            ]],
            'huy' => ['Bùi Gia Huy', '0977246810', [
                'tom' => ['Tôm', 'cat', 'Mèo ta', PetSex::Male, 365, 3.5, PetSize::S, ['is_neutered' => true]],
            ]],
            'hong' => ['Trương Thị Hồng', '0909111222', ['milo' => ['Milo', 'dog', 'Phốc sóc (Pomeranian)', PetSex::Male, 2 * 365, 3.1, PetSize::S, []]]],
            'duc' => ['Phan Văn Đức', '0918333444', ['kem' => ['Kem', 'dog', 'Shih Tzu', PetSex::Female, 4 * 365, 6.4, PetSize::S, ['chronic_conditions' => 'Viêm da mãn tính']]]],
            'tam' => ['Lưu Thanh Tâm', '0933666777', ['muop' => ['Mướp', 'cat', 'Mèo ta', PetSex::Female, 3 * 365, 4.0, PetSize::S, []]]],
            'chau' => ['Ngô Bảo Châu', '0987222111', ['rex' => ['Rex', 'dog', 'Husky', PetSex::Male, 3 * 365, 24, PetSize::L, ['behavior_notes' => 'Hay giật dây']]]],
            'thu' => ['Mai Anh Thư', '0905888999', ['bong' => ['Bông', 'dog', 'Poodle', PetSex::Female, 365, 3.8, PetSize::S, []]]],
            'vinh' => ['Đinh Quang Vinh', '0915444555', ['dau' => ['Đậu', 'dog', 'Chihuahua', PetSex::Male, 2 * 365, 2.2, PetSize::Xs, ['behavior_notes' => 'Cắn người lạ']]]],
            'phuong' => ['Tạ Minh Phương', '0939777000', ['mit' => ['Mít', 'dog', 'Corgi', PetSex::Male, 2 * 365, 11, PetSize::M, []]]],
            'oanh' => ['Hà Kiều Oanh', '0968135790', ['na' => ['Na', 'cat', 'Mèo Ba Tư', PetSex::Female, 6 * 365, 4.5, PetSize::M, []]]],
            'yen' => ['Vũ Hải Yến', '0901246135', ['gau' => ['Gấu', 'dog', 'Alaska Malamute', PetSex::Female, 4 * 365, 38, PetSize::Xl, []]]],
            'han' => ['Châu Ngọc Hân', '0976864202', ['bin' => ['Bin', 'dog', 'Beagle', PetSex::Male, 3 * 365, 12, PetSize::M, []]]],
            'son' => ['Kiều Thanh Sơn', '0944579135', ['ben' => ['Ben', 'dog', 'Phốc sóc (Pomeranian)', PetSex::Male, 365, 2.9, PetSize::Xs, []]]],
        ];

        foreach ($list as $key => [$name, $phone, $pets]) {
            $this->customers[$key] = Customer::query()->create(['name' => $name, 'phone' => '+84'.substr($phone, 1)]);

            foreach ($pets as $petKey => [$petName, $species, $breed, $sex, $ageDays, $kg, $size, $extra]) {
                $this->pets[$petKey] = Pet::query()->create([
                    'customer_id' => $this->customers[$key]->id,
                    'species_id' => $this->species[$species],
                    'breed_id' => $this->breeds[$breed],
                    'name' => $petName,
                    'sex' => $sex,
                    'birth_date' => $this->today->subDays($ageDays)->toDateString(),
                    'weight_kg' => $kg,
                    'size' => $size,
                    ...$extra,
                ]);
            }
        }

        // Mochi's medical record (opened at an earlier exam) → HS-000101.
        $record = MedicalRecord::query()->create([
            'pet_id' => $this->pets['mochi']->id,
            'record_no' => 'HS-000101',
            'opened_at' => $this->at('09:30', -60),
            'opened_by' => $this->staff['ha']->id,
        ]);

        MedicalEntry::query()->create([
            'medical_record_id' => $record->id,
            'author_staff_id' => $this->staff['ha']->id,
            'symptoms' => 'Ngứa, nổi mẩn đỏ sau khi tắm',
            'vitals' => ['temperature_c' => 38.6, 'heart_rate' => 110],
            'diagnosis' => 'Viêm da dị ứng do dầu gội có hương liệu',
            'treatment' => 'Kháng histamine 5 ngày, đổi dầu gội không mùi',
            'care_instructions' => 'Chỉ dùng dầu gội không mùi; tái khám nếu còn ngứa sau 1 tuần.',
            'internal_notes' => 'Chủ nuôi hay tự mua dầu gội ngoài, nhắc lại khi đến spa.',
            'recorded_at' => $this->at('09:45', -60),
            'editable_until' => $this->at('09:45', -59),
        ]);
    }

    private function pawHouseCare(): void
    {
        $vaccine7 = Vaccine::query()->create(['name' => 'Vaccine 7 bệnh', 'kind' => VaccineKind::Vaccine, 'species_id' => $this->species['dog'], 'manufacturer' => 'Zoetis']);
        $dewormDog = Vaccine::query()->create(['name' => 'Tẩy giun (chó)', 'kind' => VaccineKind::Dewormer, 'species_id' => $this->species['dog'], 'manufacturer' => 'Bayer']);
        $dewormCat = Vaccine::query()->create(['name' => 'Tẩy giun (mèo)', 'kind' => VaccineKind::Dewormer, 'species_id' => $this->species['cat'], 'manufacturer' => 'Bayer']);

        $seven = $this->protocol('Vaccine 7 bệnh (chó)', $vaccine7, [
            [1, 42, 0, false, null],
            [2, null, 21, false, null],
            [3, null, 21, false, null],
            [4, null, 365, true, 365], // yearly booster
        ]);
        $this->protocol('Tẩy giun (chó)', $dewormDog, [[1, 14, 0, true, 90]]);
        $catDeworm = $this->protocol('Tẩy giun (mèo)', $dewormCat, [[1, 14, 0, true, 90]]);

        // Sushi: dose 2 of 4 given, dose 3 due today.
        PetVaccination::query()->create([
            'pet_id' => $this->pets['sushi']->id, 'vaccine_id' => $vaccine7->id, 'protocol_id' => $seven->id, 'step_no' => 1,
            'administered_on' => $this->day(-35), 'administered_by' => $this->staff['ha']->id, 'batch_no' => 'VG7-2408',
            'next_due_on' => $this->day(-14), 'superseded_at' => $this->at('10:00', -14), 'status' => VaccinationStatus::Given,
        ]);
        PetVaccination::query()->create([
            'pet_id' => $this->pets['sushi']->id, 'vaccine_id' => $vaccine7->id, 'protocol_id' => $seven->id, 'step_no' => 2,
            'administered_on' => $this->day(-21), 'administered_by' => $this->staff['ha']->id, 'batch_no' => 'VG7-2409',
            'next_due_on' => $this->day(0), 'status' => VaccinationStatus::Given,
        ]);

        // Tôm: deworming 12 days overdue.
        PetVaccination::query()->create([
            'pet_id' => $this->pets['tom']->id, 'vaccine_id' => $dewormCat->id, 'protocol_id' => $catDeworm->id, 'step_no' => 1,
            'administered_on' => $this->day(-102), 'administered_by' => $this->staff['tri']->id, 'batch_no' => 'DW-2406',
            'next_due_on' => $this->day(-12), 'status' => VaccinationStatus::Given,
        ]);
    }

    private function pawHouseToday(Branch $q3): void
    {
        $s = $this->staff;
        $p = $this->pets;
        $v = $this->services;
        $booked = AppointmentItemStatus::Booked;
        $in = AppointmentItemStatus::CheckedIn;

        // 14 appointments at Q3 today, every status. [pet, service, staff] per item.
        $a = [
            'lu' => $this->appointment('nam', '08:00', [[$p['lu'], $v['exam'], $s['ha']]], status: $in),
            'milo' => $this->appointment('hong', '08:30', [[$p['milo'], $v['bath'], $s['ngocanh']]], status: $in),
            'kem' => $this->appointment('duc', '09:00', [[$p['kem'], $v['exam'], $s['tri']]], status: $in),
            'rex' => $this->appointment('chau', '09:30', [[$p['rex'], $v['combo'], $s['bao']]], status: $in),
            'tom' => $this->appointment('huy', '10:00', [[$p['tom'], $v['exam'], $s['ha']]], status: $in),
            'muop' => $this->appointment('tam', '10:30', [[$p['muop'], $v['exam'], $s['ha']]], status: AppointmentItemStatus::Cancelled),
            'bong' => $this->appointment('thu', '11:00', [[$p['bong'], $v['trim'], $s['ngocanh']]], status: AppointmentItemStatus::NoShow),
            'dau' => $this->appointment('vinh', '13:00', [[$p['dau'], $v['exam'], null]], AppointmentSource::Online, $booked),
            'mit' => $this->appointment('phuong', '13:30', [[$p['mit'], $v['bath'], null]], AppointmentSource::Online, $booked),
            // Two pets, one appointment: Mochi to the spa, Bơ to the vet.
            'lan' => $this->appointment('lan', '14:00', [[$p['mochi'], $v['bath'], $s['bao']], [$p['bo'], $v['exam'], $s['ha']]], status: $booked),
            'na' => $this->appointment('oanh', '15:00', [[$p['na'], $v['ultrasound'], $s['tri']]], status: $booked),
            'sushi' => $this->appointment('linh', '16:00', [[$p['sushi'], $v['vaccine'], $s['ha']]], status: $booked),
            'gau' => $this->appointment('yen', '17:00', [[$p['gau'], $v['nails'], null]], status: $booked),
            'bin' => $this->appointment('han', '18:00', [[$p['bin'], $v['blood'], $s['tri']]], status: $booked),
        ];

        // Till: Két 1 at Q3 open since 07:50, run by Mai.
        $drawer = CashDrawer::query()->where('branch_id', $q3->id)->firstOrFail();
        $session = CashSession::query()->create([
            'branch_id' => $q3->id, 'drawer_id' => $drawer->id, 'opened_by' => $s['mai']->id,
            'opened_at' => $this->at('07:50'), 'opening_amount' => 2_000_000,
        ]);

        // Queue: 9 visits (6 medical, 3 grooming), incl. 1 emergency and 1 stray.
        $stray = Customer::query()->where('system_branch_id', $q3->id)->firstOrFail();
        $strayPet = Pet::query()->create([
            'customer_id' => $stray->id, 'species_id' => $this->species['cat'], 'name' => 'Thú hoang #3',
            'sex' => PetSex::Unknown, 'weight_kg' => 2, 'size' => PetSize::Xs, 'is_stray' => true,
            'behavior_notes' => 'Hoảng sợ, cần đeo găng',
        ]);

        $done = VisitStatus::Done;
        $visits = [
            $this->visitFor($q3, 1, $a['lu'], $done, '07:55', $s['ha'], started: '08:05', finished: '08:35'),
            $this->visitFor($q3, 2, $a['milo'], $done, '08:25', $s['ngocanh'], started: '08:30', finished: '09:30'),
            $this->visitFor($q3, 3, $a['kem'], VisitStatus::InProgress, '08:55', $s['tri'], started: '10:50'),
            $this->visitFor($q3, 4, $a['rex'], VisitStatus::InProgress, '09:20', $s['bao'], started: '09:35'),
            $this->visitFor($q3, 5, $a['tom'], VisitStatus::Waiting, '10:05', null),
            $this->walkIn($q3, 6, 'son', $p['ben'], ServiceGroup::Medical, '10:20', 'Ho, chảy nước mũi'),
            $this->walkIn($q3, 7, 'son', $p['ben'], ServiceGroup::Grooming, '10:21', 'Cắt móng'),
            $this->visit($q3, 8, null, null, ServiceGroup::Medical, VisitStatus::InProgress, '10:40', $s['ha'], [
                'is_emergency' => true, 'emergency_label' => 'Chó cấp cứu 10:40', 'reason' => 'Bị xe tông, chảy máu chân sau', 'started_at' => $this->at('10:42'),
            ]),
            $this->visit($q3, 9, $stray, $strayPet, ServiceGroup::Medical, VisitStatus::Waiting, '10:55', null, [
                'reason' => 'Mèo hoang bị thương chân, người đi đường mang tới', 'weight_kg' => 2,
            ]),
        ];

        // Lu's first exam today opens record HS-000102.
        $luRecord = MedicalRecord::query()->create([
            'pet_id' => $p['lu']->id, 'record_no' => 'HS-000102', 'opened_at' => $this->at('08:05'), 'opened_by' => $s['ha']->id,
        ]);
        MedicalEntry::query()->create([
            'medical_record_id' => $luRecord->id, 'visit_id' => $visits[0]->id, 'author_staff_id' => $s['ha']->id,
            'symptoms' => 'Kiểm tra sức khỏe định kỳ', 'diagnosis' => 'Khỏe mạnh, hơi thừa cân',
            'care_instructions' => 'Giảm 10% khẩu phần, đi bộ 30 phút mỗi ngày.', 'follow_up_on' => $this->day(90),
            'recorded_at' => $this->at('08:30'), 'editable_until' => $this->at('08:30', 1),
        ]);
        DB::table('tenant_counters')->insert(['tenant_id' => $q3->tenant_id, 'name' => 'medical_record', 'last_value' => 102]);

        // One service draft per customer opened at intake (strays and the emergency have none yet).
        $byCustomer = [];
        foreach ($visits as $visit) {
            if ($visit->customer_id !== null && $visit->customer_id !== $stray->id) {
                $byCustomer[$visit->customer_id][] = $visit;
            }
        }

        $invoiceNo = 0;
        foreach ($byCustomer as $customerId => $customerVisits) {
            $invoice = $this->serviceInvoice($q3, $customerId, $this->day(0), $customerVisits, $s['mai']);

            $allDone = collect($customerVisits)->every(fn (Visit $visit): bool => $visit->status === $done);
            if ($allDone) {
                $this->complete($invoice, $q3, ++$invoiceNo, $s['mai'], $session, $invoiceNo === 1 ? PaymentMethod::Cash : PaymentMethod::BankTransfer);
            }
        }

        // Two drafts left over from yesterday (no automatic void; the dashboard warns).
        $yesterdayVisit = $this->visit($q3, 1, $this->customers['thu'], $p['bong'], ServiceGroup::Grooming, $done, '16:00', $s['ngocanh'], [
            'started_at' => $this->at('16:10', -1), 'finished_at' => $this->at('17:00', -1),
        ], dayOffset: -1);
        $this->visitItem($yesterdayVisit, $this->services['trim'], $p['bong'], $s['ngocanh']);
        $this->serviceInvoice($q3, $this->customers['thu']->id, $this->day(-1), [$yesterdayVisit], $s['mai']);

        $retail = Invoice::query()->create([
            'branch_id' => $q3->id, 'kind' => InvoiceKind::Retail, 'payer_phone' => '+84908765432',
            'business_date' => $this->day(-1), 'created_by' => $s['mai']->id,
        ]);
        $this->line($retail, InvoiceLineType::Custom, 'Vòng cổ phản quang size M', 1, 120_000, $s['mai']);
        $this->totals($retail);

        DB::table('branch_daily_counters')->insert([
            ['tenant_id' => $q3->tenant_id, 'branch_id' => $q3->id, 'date' => $this->day(0), 'name' => 'queue', 'last_value' => 9],
            ['tenant_id' => $q3->tenant_id, 'branch_id' => $q3->id, 'date' => $this->day(0), 'name' => 'invoice', 'last_value' => $invoiceNo],
            ['tenant_id' => $q3->tenant_id, 'branch_id' => $q3->id, 'date' => $this->day(-1), 'name' => 'queue', 'last_value' => 1],
        ]);

        DB::table('tenant_usage')->insert([
            'tenant_id' => $q3->tenant_id,
            'period' => $this->today->format('Y-m'),
            'appointments_count' => count($a),
        ]);
    }

    // ---------------------------------------------------------------- Other shops

    private function meowClinic(): void
    {
        $shop = $this->provision('Meow Clinic', 'meow-clinic', 'starter', TenantStatus::PastDue, $this->at('23:59', -3),
            ['MC1', 'Meow Clinic Thủ Đức', '9 Võ Văn Ngân, Thủ Đức, TP.HCM', '+842837200009'],
            ['Phạm Thị Thu', 'thu@meowclinic.test', null],
        );

        $this->context->runAs($shop->tenant, function () use ($shop): void {
            $this->owner($shop, 'Phạm Thị Thu', 'thu@meowclinic.test', 'Chủ shop · Bác sĩ', '#7C3AED', ['vet']);

            Service::query()->create(['code' => 'KB01', 'name' => 'Khám mèo', 'service_group' => ServiceGroup::Medical, 'duration_min' => 30, 'price' => 200_000]);

            // Same phone as a Paw House customer: a separate record, invisible from Paw House.
            $lan = Customer::query()->create(['name' => 'Nguyễn Thị Lan', 'phone' => '+84903123456', 'notes' => 'Khách của Meow Clinic']);
            Pet::query()->create([
                'customer_id' => $lan->id, 'species_id' => $this->species['cat'], 'breed_id' => $this->breeds['Mèo ta'],
                'name' => 'Mun', 'sex' => PetSex::Female, 'size' => PetSize::S, 'weight_kg' => 3.2,
            ]);
        });
    }

    private function smallShop(string $name, string $slug, string $plan, TenantStatus $status, CarbonImmutable $expiresAt, string $code, string $owner, string $email, ?string $suspendedReason = null): void
    {
        $shop = $this->provision($name, $slug, $plan, $status, $expiresAt, [$code.'1', $name, null, null], [$owner, $email, null],
            $suspendedReason === null ? [] : ['suspended_at' => $this->at('00:00', -3), 'suspended_reason' => $suspendedReason],
        );

        $this->context->runAs($shop->tenant, fn () => $this->owner($shop, $owner, $email, 'Chủ shop', '#0D9488', []));
    }

    // ---------------------------------------------------------------- Builders

    /**
     * @param  array{0: string, 1: string, 2: string|null, 3: string|null}  $branch  code, name, address, phone
     * @param  array{0: string, 1: string, 2: string|null}  $owner  name, e-mail, phone
     * @param  array<string, mixed>  $extra
     */
    private function provision(string $name, string $slug, string $planCode, TenantStatus $status, CarbonImmutable $expiresAt, array $branch, array $owner, array $extra = []): ProvisionedShop
    {
        $plan = Plan::query()->where('code', $planCode)->firstOrFail();
        $trial = $status === TenantStatus::Trial;

        $shop = app(ProvisionTenant::class)->handle($this->admin, new NewShop(
            name: $name,
            slug: $slug,
            plan: $plan,
            branchCode: $branch[0],
            branchName: $branch[1],
            ownerName: $owner[0],
            ownerEmail: $owner[1],
            ownerPhone: $owner[2],
            trialDays: $trial ? max(1, (int) ceil(now()->diffInDays($expiresAt))) : null,
            paidFrom: $trial ? null : $expiresAt->subYear(),
            paidUntil: $trial ? null : $expiresAt,
            amount: $trial ? null : ($plan->code === 'pro' ? '6000000' : '2400000'),
            paymentRef: $trial ? null : 'CK-'.Str::upper(Str::random(8)),
            timezone: self::TZ,
            branchAddress: $branch[2],
            branchPhone: $branch[3],
        ), sendInvitation: false);

        // States provisioning never produces (past due, suspended) and exact demo expiry times, set
        // once the owners have joined (nobody can join a suspended shop).
        $this->states[] = [$shop->tenant, ['status' => $status, 'plan_expires_at' => $expiresAt, ...$extra]];
        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->tenant->getKey());

        return $shop;
    }

    /**
     * Accepts the owner invitation as the demo owner would on /invite/{token}.
     *
     * @param  list<string>  $extraRoles
     */
    private function owner(ProvisionedShop $shop, string $name, string $email, string $title, string $color, array $extraRoles): Staff
    {
        $user = $this->user($name, $email);
        $staff = app(AcceptInvitation::class)->handle($shop->tenant, $shop->invitation, $user);
        $staff->forceFill(['title' => $title, 'color' => $color])->save();

        if ($extraRoles !== []) {
            $user->assignRole($extraRoles); // team = current shop (set in provision())
        }

        return $staff;
    }

    private function branch(string $code, string $name, ?string $address, ?string $phone): Branch
    {
        return app(CreateBranch::class)->handle(['code' => $code, 'name' => $name, 'address' => $address, 'phone' => $phone]);
    }

    private function user(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  list<Branch>  $extraBranches
     * @param  list<string>  $roles
     */
    private function member(Branch $primary, array $extraBranches, string $name, string $title, ?string $color, ?User $user, array $roles = []): Staff
    {
        $staff = Staff::query()->create([
            'user_id' => $user?->id,
            'primary_branch_id' => $primary->id,
            'display_name' => $name,
            'title' => $title,
            'color' => $color,
        ]);

        if ($user !== null && $roles !== []) {
            $user->assignRole($roles); // team = current shop (set in tenant())
        }

        $staff->branches()->attach($primary->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => true]);
        foreach ($extraBranches as $branch) {
            $staff->branches()->attach($branch->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => false]);
        }

        return $staff;
    }

    /**
     * @param  list<array{0: string, 1: PetSize|null, 2: int|null, 3: int|null}>  $rules  [species, size, minutes, price]
     */
    private function service(string $code, string $name, ServiceGroup $group, int $duration, int $buffer, int $price, array $rules = [], bool $isCombo = false): Service
    {
        $service = Service::query()->create([
            'code' => $code,
            'name' => $name,
            'service_group' => $group,
            'is_combo' => $isCombo,
            'duration_min' => $duration,
            'buffer_min' => $buffer,
            'price' => $price,
        ]);

        foreach ($rules as [$species, $size, $minutes, $rulePrice]) {
            ServicePriceRule::query()->create([
                'service_id' => $service->id,
                'species_id' => $this->species[$species],
                'size' => $size,
                'duration_min' => $minutes,
                'price' => $rulePrice,
            ]);
        }

        return $service;
    }

    /**
     * @param  list<array{0: int, 1: int|null, 2: int, 3: bool, 4: int|null}>  $steps  [no, min age, offset, recurring, every]
     */
    private function protocol(string $name, Vaccine $vaccine, array $steps): CareProtocol
    {
        $protocol = CareProtocol::query()->create(['name' => $name, 'species_id' => $vaccine->species_id, 'vaccine_id' => $vaccine->id]);

        foreach ($steps as [$no, $minAge, $offset, $recurring, $every]) {
            $protocol->steps()->create([
                'step_no' => $no, 'min_age_days' => $minAge, 'offset_days' => $offset,
                'is_recurring' => $recurring, 'repeat_every_days' => $every,
            ]);
        }

        return $protocol;
    }

    /**
     * Duration / price for a pet: most specific rule wins (breed → species + size → species → default).
     *
     * @return array{0: int, 1: int} [minutes, price]
     */
    private function quote(Service $service, Pet $pet): array
    {
        $rules = ServicePriceRule::query()->where('service_id', $service->id)->get();

        $rule = $rules->first(fn (ServicePriceRule $r): bool => $pet->breed_id !== null && $r->breed_id === $pet->breed_id)
            ?? $rules->first(fn (ServicePriceRule $r): bool => $r->breed_id === null && $r->species_id === $pet->species_id && $r->size === $pet->size)
            ?? $rules->first(fn (ServicePriceRule $r): bool => $r->breed_id === null && $r->species_id === $pet->species_id && $r->size === null);

        return [
            (int) ($rule->duration_min ?? $service->duration_min),
            (int) ($rule->price ?? $service->price),
        ];
    }

    /**
     * @param  list<array{0: Pet, 1: Service, 2: Staff|null}>  $lines
     */
    private function appointment(string $customer, string $time, array $lines, AppointmentSource $source = AppointmentSource::Staff, AppointmentItemStatus $status = AppointmentItemStatus::Booked): Appointment
    {
        $startsAt = $this->at($time);
        $items = [];
        $endsAt = $startsAt;

        foreach ($lines as [$pet, $service, $staff]) {
            [$minutes, $price] = $this->quote($service, $pet);
            $itemEnds = $startsAt->addMinutes($minutes + $service->buffer_min);
            $endsAt = $itemEnds->max($endsAt);
            $items[] = [$pet, $service, $staff, $minutes, $price, $itemEnds];
        }

        $headerStatus = match ($status) {
            AppointmentItemStatus::Booked => AppointmentStatus::Confirmed,
            AppointmentItemStatus::CheckedIn => AppointmentStatus::CheckedIn,
            AppointmentItemStatus::Cancelled => AppointmentStatus::Cancelled,
            AppointmentItemStatus::NoShow => AppointmentStatus::NoShow,
        };

        $createdAt = $this->at('20:00', -3);
        $appointment = Appointment::query()->create([
            'branch_id' => $this->staff['mai']->primary_branch_id,
            'customer_id' => $this->customers[$customer]->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source' => $source,
            'status' => $headerStatus,
            'created_by_staff_id' => $source === AppointmentSource::Staff ? $this->staff['mai']->id : null,
            'customer_note' => $source === AppointmentSource::Online ? 'Đặt qua website' : null,
            'cancelled_at' => $status === AppointmentItemStatus::Cancelled ? $this->at('07:30') : null,
            'cancelled_by_type' => $status === AppointmentItemStatus::Cancelled ? ActorType::Customer : null,
            'cancel_reason' => $status === AppointmentItemStatus::Cancelled ? 'Khách bận đột xuất' : null,
            'no_show_at' => $status === AppointmentItemStatus::NoShow ? $this->at('11:30') : null,
            'created_at' => $createdAt,
        ]);

        foreach ($items as [$pet, $service, $staff, $minutes, $price, $itemEnds]) {
            $appointment->items()->create([
                'branch_id' => $appointment->branch_id,
                'customer_id' => $appointment->customer_id,
                'pet_id' => $pet->id,
                'service_id' => $service->id,
                'service_group' => $service->service_group,
                'starts_at' => $startsAt,
                'ends_at' => $itemEnds,
                'duration_min' => $minutes,
                'reference_price' => $price,
                'status' => $status,
                'assigned_staff_id' => $staff?->id,
            ]);
        }

        $change = match ($status) {
            AppointmentItemStatus::Cancelled => [AppointmentChangeAction::Cancelled, ActorType::Customer, null, 'Khách bận đột xuất', $this->at('07:30')],
            AppointmentItemStatus::NoShow => [AppointmentChangeAction::NoShow, ActorType::Staff, $this->staff['mai']->id, null, $this->at('11:30')],
            default => null,
        };

        if ($change !== null) {
            AppointmentChange::query()->create([
                'appointment_id' => $appointment->id,
                'action' => $change[0],
                'actor_type' => $change[1],
                'actor_staff_id' => $change[2],
                'reason' => $change[3],
                'created_at' => $change[4],
            ]);
        }

        return $appointment;
    }

    /**
     * Check-in of a booked appointment (one pet, one group here): Visit + link items + history.
     */
    private function visitFor(Branch $branch, int $queueNo, Appointment $appointment, VisitStatus $status, string $checkedIn, ?Staff $staff, ?string $started = null, ?string $finished = null): Visit
    {
        /** @var AppointmentItem $item */
        $item = $appointment->items()->firstOrFail();
        $checkedInAt = $this->at($checkedIn);

        // Within ±walk_in_priority_minutes of the slot: queue by appointment time, else by arrival.
        $priorityAt = abs($checkedInAt->diffInMinutes($item->starts_at)) <= 15 ? $item->starts_at : $checkedInAt;

        $visit = $this->visit($branch, $queueNo, $this->customers[$this->customerKey($appointment)], $this->petById($item->pet_id), $item->service_group, $status, $checkedIn, $staff, [
            'priority_at' => $priorityAt,
            'started_at' => $started === null ? null : $this->at($started),
            'finished_at' => $finished === null ? null : $this->at($finished),
        ]);

        $item->forceFill(['visit_id' => $visit->id])->save();

        AppointmentChange::query()->create([
            'appointment_id' => $appointment->id,
            'appointment_item_id' => $item->id,
            'action' => AppointmentChangeAction::CheckedIn,
            'actor_type' => ActorType::Staff,
            'actor_staff_id' => $this->staff['mai']->id,
            'created_at' => $checkedInAt,
        ]);

        $this->visitItem($visit, $this->serviceById($item->service_id), $this->petById($item->pet_id), $staff);

        return $visit;
    }

    private function walkIn(Branch $branch, int $queueNo, string $customer, Pet $pet, ServiceGroup $group, string $time, string $reason): Visit
    {
        $visit = $this->visit($branch, $queueNo, $this->customers[$customer], $pet, $group, VisitStatus::Waiting, $time, null, ['reason' => $reason]);
        $this->visitItem($visit, $group === ServiceGroup::Medical ? $this->services['exam'] : $this->services['nails'], $pet, null);

        return $visit;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function visit(Branch $branch, int $queueNo, ?Customer $customer, ?Pet $pet, ServiceGroup $group, VisitStatus $status, string $checkedIn, ?Staff $staff, array $extra = [], int $dayOffset = 0): Visit
    {
        $checkedInAt = $this->at($checkedIn, $dayOffset);

        return Visit::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer?->id,
            'pet_id' => $pet?->id,
            'service_group' => $group,
            'visit_date' => $this->day($dayOffset),
            'queue_no' => $queueNo,
            'status' => $status,
            'priority_at' => $checkedInAt,
            'assigned_staff_id' => $staff?->id,
            'checked_in_at' => $checkedInAt,
            'weight_kg' => $pet?->weight_kg,
            'created_by' => $this->staff['mai']->id,
            ...$extra,
        ]);
    }

    private function visitItem(Visit $visit, Service $service, Pet $pet, ?Staff $staff): VisitItem
    {
        [, $price] = $this->quote($service, $pet);

        return VisitItem::query()->create([
            'visit_id' => $visit->id,
            'item_type' => VisitItemType::Service,
            'service_id' => $service->id,
            'name' => $service->name,
            'quantity' => 1,
            'unit_price' => $price,
            'performed_by' => $staff?->id,
        ]);
    }

    /**
     * @param  list<Visit>  $visits
     */
    private function serviceInvoice(Branch $branch, int $customerId, string $businessDate, array $visits, Staff $by): Invoice
    {
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'kind' => InvoiceKind::Service,
            'customer_id' => $customerId,
            'business_date' => $businessDate,
            'created_by' => $by->id,
        ]);

        foreach ($visits as $visit) {
            $visit->forceFill(['invoice_id' => $invoice->id])->save();

            foreach (VisitItem::query()->where('visit_id', $visit->id)->where('is_billable', true)->get() as $item) {
                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'line_type' => InvoiceLineType::VisitItem,
                    'visit_item_id' => $item->id,
                    'pet_id' => $visit->pet_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => (int) $item->quantity * (int) $item->unit_price,
                    'created_by' => $by->id,
                ]);
            }
        }

        return $this->totals($invoice);
    }

    private function line(Invoice $invoice, InvoiceLineType $type, string $name, int $quantity, int $price, Staff $by): void
    {
        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'line_type' => $type,
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $quantity * $price,
            'created_by' => $by->id,
        ]);
    }

    private function totals(Invoice $invoice): Invoice
    {
        $total = (int) InvoiceLine::query()->where('invoice_id', $invoice->id)->sum('line_total');
        $invoice->forceFill(['subtotal' => $total, 'tax_amount' => 0, 'total' => $total])->save();

        return $invoice;
    }

    private function complete(Invoice $invoice, Branch $branch, int $seq, Staff $by, CashSession $session, PaymentMethod $method): void
    {
        $paidAt = $this->at('09:40');

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'kind' => PaymentKind::Payment,
            'method' => $method,
            'amount' => $invoice->total,
            'reference' => $method === PaymentMethod::Cash ? null : 'FT'.Str::upper(Str::random(10)),
            'cash_session_id' => $method === PaymentMethod::Cash ? $session->id : null,
            'received_by_staff_id' => $by->id,
            'paid_at' => $paidAt,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $invoice->forceFill([
            'status' => InvoiceStatus::Completed,
            'code' => sprintf('%s-%s-%03d', $branch->code, $this->today->format('Ymd'), $seq),
            'completed_at' => $paidAt,
            'completed_by' => $by->id,
        ])->save();
    }

    private function customerKey(Appointment $appointment): string
    {
        foreach ($this->customers as $key => $customer) {
            if ($customer->id === $appointment->customer_id) {
                return $key;
            }
        }

        throw new LogicException('Unknown demo customer.');
    }

    private function petById(int $id): Pet
    {
        foreach ($this->pets as $pet) {
            if ($pet->id === $id) {
                return $pet;
            }
        }

        throw new LogicException('Unknown demo pet.');
    }

    private function serviceById(int $id): Service
    {
        foreach ($this->services as $service) {
            if ($service->id === $id) {
                return $service;
            }
        }

        throw new LogicException('Unknown demo service.');
    }

    /**
     * A shop-local wall-clock time on today (+ offset days), converted to UTC for storage.
     */
    private function at(string $time, int $dayOffset = 0): CarbonImmutable
    {
        return $this->today->addDays($dayOffset)->setTimeFromTimeString($time)->utc();
    }

    /**
     * A shop-local business date (DATE column).
     */
    private function day(int $offset): string
    {
        return $this->today->addDays($offset)->toDateString();
    }
}
