<?php

namespace Tests\Feature;

use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\NetworkSummary;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GenderAvatarTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['male'])]
    #[TestWith(['female'])]
    public function test_registration_saves_gender_for_cartoon_avatar(string $gender): void
    {
        Notification::fake();

        $this->post(route('register.store'), ['name' => 'Calon Mitra', 'email' => 'mitra@example.test', 'gender' => $gender, 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'mitra@example.test')->firstOrFail();
        $this->assertSame($gender, $user->gender);
        $this->assertNull($user->avatar);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_profile_photo_and_gender_are_available_in_the_network(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $partner = Partner::factory()->active()->create(['user_id' => $user->id]);
        $photo = UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC6kAAAAASUVORK5CYII='));

        $this->actingAs($user)->post(route('profile.update'), ['_method' => 'PATCH', 'name' => $user->name, 'email' => $user->email, 'gender' => 'female', 'photo' => $photo])
            ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $user->refresh();
        Storage::disk('public')->assertExists($user->profile_photo_path);
        $this->assertSame('female', $user->gender);
        $graph = app(NetworkSummary::class)->forRoot();
        $node = collect($graph['nodes'])->firstWhere('id', $partner->id);
        $this->assertSame('female', $node['gender']);
        $this->assertSame($user->avatar, $node['avatar']);
    }

    public function test_invalid_gender_and_non_image_upload_do_not_modify_profile(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['gender' => 'male']);

        $this->actingAs($user)->post(route('profile.update'), ['_method' => 'PATCH', 'name' => $user->name, 'email' => $user->email, 'gender' => 'invalid', 'photo' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml')])
            ->assertSessionHasErrors(['gender', 'photo']);

        $this->assertSame('male', $user->fresh()->gender);
        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
