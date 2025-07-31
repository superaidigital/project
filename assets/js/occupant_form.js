function showOccupantForm(shelterId) {
    document.getElementById('occupantFormPopup').classList.remove('hidden');
    document.getElementById('occupantFormPopup').classList.add('flex');
}

function closeOccupantForm() {
    document.getElementById('occupantFormPopup').classList.add('hidden');
    document.getElementById('occupantFormPopup').classList.remove('flex');
}

document.getElementById('occupantForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    try {
        const response = await fetch('api/update_occupants.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            alert('บันทึกข้อมูลสำเร็จ');
            closeOccupantForm();
            // Refresh the page or update the display
            location.reload();
        } else {
            alert(result.message || 'เกิดข้อผิดพลาด');
        }
    } catch (error) {
        console.error(error);
        alert('เกิดข้อผิดพลาด');
    }
});