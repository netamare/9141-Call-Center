import {Schema,model,models} from "mongoose";
const NotificationSchema=new Schema({user:{type:Schema.Types.ObjectId,ref:"User"},title:{type:String,required:true},message:{type:String,required:true},type:{type:String,enum:["success","error","warning","assignment","status"],default:"status"},read:{type:Boolean,default:false},event:{type:Schema.Types.ObjectId,ref:"Event"}},{timestamps:true});
export default models.Notification||model("Notification",NotificationSchema);
