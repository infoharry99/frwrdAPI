<?php

namespace Tests\Feature;

use Tests\TestCase;

class EndToEndApiTest extends TestCase
{
    public function test_server_test_endpoint()
    {
        $response = $this->get('/api/test');
        $response->assertStatus(200);
        $this->assertEquals('Server is working!', $response->getContent());
    }

    public function test_packages_list_endpoint()
    {
        $response = $this->getJson('/api/allpackega');
        $response->assertStatus(200);
        $response->assertJsonIsArray();
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_apply_coupon_endpoint()
    {
        $response = $this->postJson('/api/apply-coupon', [
            'code' => 'WELCOME50',
            'userId' => 5017056,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'discount_type' => 'flat',
        ]);
    }

    public function test_holidays_endpoint()
    {
        $response = $this->getJson('/api/holidays');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'count', 'data']);
    }

    public function test_payment_status_endpoint()
    {
        $response = $this->getJson('/api/payment-status/55abdac6-0dc5-46e8-a782-9a94460e3627');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'SUCCESS',
        ]);
    }

    public function test_uploads_static_serving()
    {
        $response = $this->get('/api/uploads/Capture.PNG');
        $response->assertStatus(200);
    }

    public function test_admin_branch1017_clients_endpoint()
    {
        $response = $this->getJson('/api/admin/all-clientsBranch1017');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'total', 'page', 'data']);
    }
}
