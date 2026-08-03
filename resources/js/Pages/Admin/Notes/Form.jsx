import AdminLayout from '../../../Layouts/AdminLayout';

export default function Form({ note }) {
    return (
        <AdminLayout title={note ? 'Izohni tahrirlash' : "Izoh qo'shish"}>
            <div>{note ? 'Izohni tahrirlash' : "Izoh qo'shish"}</div>
        </AdminLayout>
    );
}
