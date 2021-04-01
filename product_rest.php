<?php
session_start();
?>
<?php
include_once("class.db.php");
if ($_SERVER["REQUEST_METHOD"] == 'GET') {
    echo json_encode(product_list(), JSON_UNESCAPED_UNICODE);
} else if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    echo json_decode(print_r(open_bill()));
}
function product_list()
{
    $db = new database();
    $db->connect();
    $sql = "SELECT Product_id,Product_code,Product_Name,
                       brand.Brand_name, unit.Unit_name,
                       product.Cost, product.Stock_Quantity
                FROM  product,brand,unit 
                WHERE product.Brand_ID = brand.Brand_id
                and   product.Unit_ID  = unit.Unit_id";
    $result = $db->query($sql);
    $db->close();
    return $result;
}

function open_bill()
{
    //1. check  have some openbill?
    //   1.1 no: create new open_bill
    //   1.2 yes: check status openbill = 1?
    //      1.2.1 yes:
    //                check product Id exist yes: update qty in bill_detail
    //                check product Id exist no:  add product to bill_detail

    $step = 1;
    $bill_id = 1;
    $bill_head = "";
    $bill_detail = "";
    $p_id = $_POST['p_id'];
    $p_qty = $_POST['p_qty'];
    $p_price = $_POST['p_price'] * $_POST['p_qty'];

    $db = new database();
    $db->connect();
    $sql = "SELECT Bill_id, Bill_status FROM bill WHERE Cus_ID='{$_SESSION['cus_id']}' order by Bill_id desc limit 1";
    $bill_result = $db->query($sql);
    if (sizeof($bill_result) == 0) {
        // insert new
        $step = "2:insert new";
        $sql = "INSERT INTO bill(Bill_id, Cus_ID, Bill_Status) VALUES ({$bill_id},'{$_SESSION['cus_id']}',0)";
        $result = $db->exec($sql);
        $sql = "INSERT INTO bill_detail(Bill_id, Product_ID, Quantity, Unit_Price)
                    VALUES ({$bill_id}, '{$p_id}', '{$p_qty}', '{$p_price}')";
        $result = $db->exec($sql);
    } else {
        // check [0][0] bill_id
        //       [0][1] bill_status
        if ($bill_result[0][1] == 0) {
            // add new product
            $step = "3:add new item";
            $sql = "INSERT INTO bill_detail(Bill_id, Product_ID, Quantity, Unit_Price)
                        VALUES ({$bill_result[0][0]}, {$p_id}, {$p_qty}, {$p_price})";
            $result = $db->exec($sql);
            if ($result == 0) {
                // update current item
                $step = "4:update item";
                $bill_id = $bill_result[0][0];
                $sql = "UPDATE `bill_detail`
                        SET `Quantity`={$p_qty}, `Unit_Price`={$p_price}
                        WHERE Bill_id={$bill_id} and Product_ID = {$p_id}";
                $result = $db->exec($sql);
                $step = "5:update complete";
            }
        }
        $sql = "SELECT * FROM bill WHERE Bill_id={$bill_result[0][0]}";
        $bill_head = $db->query($sql);
        $sql = "SELECT * FROM bill_detail WHERE Bill_id={$bill_result[0][0]}";
        $bill_detail = $db->query($sql);
    }
    //$sql = "INSERT INTO bill(Bill_id, Cus_ID, Bill_Status) VALUES (1,1,1)";
    $db->close();
    return ["step" => $step, "sql" => $sql, "bill" => $bill_head, "bill_detail" => $bill_detail];
}
?>