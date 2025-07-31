<?php
<div id="occupantFormPopup" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-11/12 max-w-2xl mx-auto">
        <form id="occupantForm" class="space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold">บันทึกข้อมูลผู้เข้าพัก/ผู้ป่วย</h3>
                <button type="button" onclick="closeOccupantForm()" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <h4 class="text-lg font-medium col-span-2">จำนวนผู้เข้าพัก</h4>
                <div>
                    <label class="block">ชาย *</label>
                    <input type="number" name="male" required class="form-input w-full" min="0">
                </div>
                <div>
                    <label class="block">หญิง *</label>
                    <input type="number" name="female" required class="form-input w-full" min="0">
                </div>
                <!-- Other occupant fields -->
                <div>
                    <label class="block">หญิงตั้งครรภ์</label>
                    <input type="number" name="pregnant" class="form-input w-full" min="0">
                </div>
                <!-- Add remaining fields... -->
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                <h4 class="text-lg font-medium col-span-2">ผู้ป่วยเรื้อรัง</h4>
                <!-- Chronic condition fields -->
            </div>

            <div class="mt-6">
                <label class="block mb-2">สถานะยอด</label>
                <select name="status" required class="form-select w-full">
                    <option value="เพิ่ม">เพิ่ม</option>
                    <option value="ลด">ลด</option>
                </select>
            </div>

            <div class="flex justify-end gap-4">
                <button type="button" onclick="closeOccupantForm()" class="btn btn-gray">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>