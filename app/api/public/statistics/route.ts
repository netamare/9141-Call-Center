import {NextResponse} from "next/server";
import {connectDB} from "@/lib/db";
import Event from "@/models/Event";

export async function GET(){try{await connectDB();const [total,resolved,critical,recent]=await Promise.all([Event.countDocuments(),Event.countDocuments({status:"Solved"}),Event.countDocuments({priority:"Critical",status:{$ne:"Solved"}}),Event.aggregate([{$group:{_id:"$category",value:{$sum:1}}},{$sort:{value:-1}},{$limit:4}])]);return NextResponse.json({total,resolved,critical,categories:recent.map(x=>({name:x._id,value:x.value}))},{headers:{"Cache-Control":"public, max-age=300"}});}catch{return NextResponse.json({total:0,resolved:0,critical:0,categories:[]});}}
